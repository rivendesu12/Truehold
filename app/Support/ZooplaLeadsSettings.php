<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;

/**
 * The Zoho password and the last run's outcome for `zoopla:leads`, kept in
 * storage/app/private/zoopla-leads.json (git-ignored). The password is
 * entered on /admin/zoopla-leads so nobody has to edit .env, and stored
 * encrypted with APP_KEY; ZOOPLA_LEADS_IMAP_PASSWORD in .env still wins.
 */
class ZooplaLeadsSettings
{
    public static function path(): string
    {
        return storage_path('app/private/zoopla-leads.json');
    }

    public static function password(): ?string
    {
        if (filled(config('services.zoopla_leads.password'))) {
            return config('services.zoopla_leads.password');
        }

        $stored = self::read()['password'] ?? null;
        if (! $stored) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return null; // APP_KEY changed; enter it again.
        }
    }

    public static function savePassword(string $password): void
    {
        // A new password makes the last run's error stale.
        self::write(['password' => Crypt::encryptString($password), 'last_run' => null, 'saved_at' => now()->toIso8601String()]);
    }

    /** @param  array{ok: bool, message: string}  $run */
    public static function recordRun(array $run): void
    {
        self::write(['last_run' => $run + ['at' => now()->toIso8601String()]]);
    }

    public static function lastRun(): ?array
    {
        return self::read()['last_run'] ?? null;
    }

    private static function read(): array
    {
        return is_file(self::path()) ? (json_decode((string) @file_get_contents(self::path()), true) ?: []) : [];
    }

    private static function write(array $changes): void
    {
        if (! is_dir(dirname(self::path()))) {
            mkdir(dirname(self::path()), 0775, true);
        }

        file_put_contents(self::path(), json_encode(array_merge(self::read(), $changes), JSON_PRETTY_PRINT), LOCK_EX);
        @chmod(self::path(), 0600);
    }
}
