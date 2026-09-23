<?php

namespace App\Support;

use RuntimeException;

/**
 * Just enough IMAP to read a mailbox: log in, EXAMINE a folder, search, fetch
 * whole messages. EXAMINE opens the folder read-only and fetches use
 * BODY.PEEK, so nothing is ever marked read, moved or deleted.
 *
 * Hand-rolled because PHP 8.4 no longer ships ext-imap and the pure-PHP
 * packages pull in extensions the server may not have.
 */
class ImapInbox
{
    /** @var resource|null */
    private $stream = null;

    private int $tag = 0;

    public function __construct(
        private string $host,
        private int $port,
        private int $timeout = 30,
    ) {}

    public function login(string $user, string $password): void
    {
        $this->stream = @stream_socket_client(
            "ssl://{$this->host}:{$this->port}", $errno, $errstr, $this->timeout
        );

        if (! $this->stream) {
            throw new RuntimeException("IMAP connect to {$this->host} failed: {$errstr}");
        }

        stream_set_timeout($this->stream, $this->timeout);
        $this->readLine(); // greeting

        $this->command('LOGIN ' . $this->quote($user) . ' ' . $this->quote($password));
    }

    public function examine(string $folder = 'INBOX'): void
    {
        $this->command('EXAMINE ' . $this->quote($folder));
    }

    /** @return int[] UIDs matching an IMAP SEARCH expression. */
    public function search(string $criteria): array
    {
        $uids = [];
        foreach ($this->command('UID SEARCH ' . $criteria) as $line) {
            if (str_starts_with($line, '* SEARCH')) {
                $uids = array_merge($uids, array_map('intval', preg_split('/\s+/', trim(substr($line, 8)), -1, PREG_SPLIT_NO_EMPTY)));
            }
        }

        return $uids;
    }

    /** The whole message as sent, headers and body. */
    public function fetchRaw(int $uid): ?string
    {
        foreach ($this->command("UID FETCH {$uid} BODY.PEEK[]", true) as $chunk) {
            if (is_array($chunk)) {
                return $chunk['literal'];
            }
        }

        return null;
    }

    public function logout(): void
    {
        if ($this->stream) {
            try {
                $this->command('LOGOUT');
            } catch (RuntimeException) {
                // Already gone; nothing to tidy.
            }
            fclose($this->stream);
            $this->stream = null;
        }
    }

    /**
     * Sends a command and reads to its tagged reply. Literals ({n} at a line
     * end) are read byte-exact and, when asked for, returned as
     * ['literal' => ...] entries between the text lines.
     */
    private function command(string $command, bool $keepLiterals = false): array
    {
        $tag = 'A' . (++$this->tag);
        fwrite($this->stream, "{$tag} {$command}\r\n");

        $out = [];
        while (true) {
            $line = $this->readLine();

            if (preg_match('/\{(\d+)\}$/', $line, $m)) {
                $literal = $this->readBytes((int) $m[1]);
                $out[] = $keepLiterals ? ['literal' => $literal] : $line;

                continue;
            }

            if (str_starts_with($line, $tag . ' ')) {
                if (! preg_match('/^' . $tag . ' OK/i', $line)) {
                    // Never echo the command: LOGIN carries the password.
                    throw new RuntimeException('IMAP ' . strtok($command, ' ') . ' failed: ' . substr($line, strlen($tag) + 1));
                }

                return $out;
            }

            $out[] = $line;
        }
    }

    private function readLine(): string
    {
        $line = fgets($this->stream);

        if ($line === false) {
            throw new RuntimeException('IMAP connection closed or timed out');
        }

        return rtrim($line, "\r\n");
    }

    private function readBytes(int $length): string
    {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($this->stream, $length - strlen($data));
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('IMAP connection closed mid-message');
            }
            $data .= $chunk;
        }

        return $data;
    }

    private function quote(string $value): string
    {
        return '"' . addcslashes($value, '"\\') . '"';
    }
}
