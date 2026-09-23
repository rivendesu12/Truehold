<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Sheets as GoogleSheets;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The agencies we work with, from the "Agencies link" tab of the Room targets
 * spreadsheet: each one's vacancy link, max tenant age, commission and the
 * agent's share. Lets Sigou answer "give me the Javier list" or "what does
 * Banksia pay" from the office's own sheet.
 *
 * Reads columns A-C and E-I only. Column D is not read, and the neighbouring
 * "Agency rules" tab (which holds account logins) is never touched.
 *
 * Refreshed from the console with the feed (properties:clear-cache); web
 * requests read the cached copy, and a failed read keeps the last good one.
 */
class AgencyDirectory
{
    protected const CACHE_KEY = 'agency_directory';
    protected const TAB = 'Agencies link';

    /** Read once per request: the commission check asks for every listing. */
    protected static ?array $memo = null;

    /** @return array<int, array{name:string, link:?string, max_age:?int, commission:?string, agent_share:?string, post_on_spareroom:?string, custom_sheet:?string}> */
    public function all(): array
    {
        return self::$memo ??= (array) Cache::get(self::CACHE_KEY, []);
    }

    public function refresh(): int
    {
        try {
            $rows = $this->fetch();
        } catch (\Throwable $e) {
            Log::warning('Agency directory read failed', ['error' => $e->getMessage()]);
            return count($this->all());
        }

        // A read that finds no agencies is a broken sheet, not no agencies.
        if ($rows) {
            Cache::forever(self::CACHE_KEY, $rows);
            self::$memo = $rows;
        }

        return count($rows);
    }

    /** The agency an agent means: "javier", "ap", "dc", "life stay". */
    public function find(?string $name): ?array
    {
        $want = self::key((string) $name);
        if ($want === '') {
            return null;
        }

        $agencies = $this->all();
        foreach ($agencies as $a) {
            if (self::key($a['name']) === $want) {
                return $a;
            }
        }
        foreach ($agencies as $a) {
            $key = self::key($a['name']);
            if (preg_match('/(^| )' . preg_quote($want, '/') . '( |$)/', $key)
                || preg_match('/(^| )' . preg_quote($key, '/') . '( |$)/', $want)) {
                return $a;
            }
        }

        return null;
    }

    public static function key(string $name): string
    {
        $name = self::plainKey($name);

        return ['ap horizon' => 'ap', 'horizon' => 'ap'][$name] ?? $name;
    }

    /** The name without company suffixes, but AP and Horizon kept apart. */
    public static function plainKey(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/\b(ltd|limited|lettings|letting|properties|property|rooms|living|homes|group|london)\b/', ' ', $name);

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]+/', ' ', $name)));
    }

    protected function fetch(): array
    {
        $client = new GoogleClient();
        $client->setScopes([GoogleSheets::SPREADSHEETS_READONLY]);
        $client->setAuthConfig(config('services.supplier_targets.credentials_path'));
        $sheets = new GoogleSheets($client);
        $id = config('services.supplier_targets.spreadsheet_id');
        $tab = "'" . self::TAB . "'";

        $left = $sheets->spreadsheets_values->get($id, $tab . '!A1:C200')->getValues() ?? [];
        $right = $sheets->spreadsheets_values->get($id, $tab . '!E1:I200')->getValues() ?? [];

        $out = [];
        foreach ($left as $i => $row) {
            $name = trim((string) ($row[0] ?? ''));
            // Header, blank rows, and the admin line at the bottom.
            if ($i === 0 || $name === '' || str_contains(strtoupper($name), 'TRUEHOLD OPS')) {
                continue;
            }
            $r = $right[$i] ?? [];
            $link = trim((string) ($row[1] ?? ''));
            // Some cells hold two links separated by slashes; keep the first.
            if (preg_match('#https?://\S+#', $link, $m)) {
                $link = rtrim($m[0], '/ ');
            } else {
                $link = null;
            }

            $out[] = [
                'name' => $name,
                'link' => $link,
                'max_age' => is_numeric($row[2] ?? null) ? (int) $row[2] : null,
                'commission' => trim((string) ($r[0] ?? '')) ?: null,
                'agent_share' => trim((string) ($r[1] ?? '')) ?: null,
                'post_on_spareroom' => trim((string) ($r[3] ?? '')) ?: null,
                'custom_sheet' => trim((string) ($r[4] ?? '')) ?: null,
            ];
        }

        return $out;
    }
}
