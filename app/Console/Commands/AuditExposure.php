<?php

namespace App\Console\Commands;

use App\Services\ScrapedListingsApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Checks that a listing link shared with a client shows the client view.
 *
 * Agents share property links, and the client opens the same URL logged out.
 * Anything the agent side knows — which agency is behind the room, the source
 * advert, whether it pays us — has to be absent from that page, and so does
 * any notice announcing that information is being withheld: a client told
 * "available to registered users" has learned the thing we were hiding.
 *
 * Checked by value, not by label. Searching for the word "agency" proves
 * nothing; searching for "Urban Base Properties" on a page that must not
 * name it proves something. Names shorter than four characters are compared
 * on word boundaries, or "AP" matches "Wapping" and every "apartment".
 */
class AuditExposure extends Command
{
    protected $signature = 'audit:exposure {--listings=10 : how many to sample} {--url= : base url to test}';

    protected $description = 'Check that shared listing links leak nothing from the agent side';

    public function handle(ScrapedListingsApiService $feed): int
    {
        $base = rtrim((string) ($this->option('url') ?: config('app.url')), '/');

        if ($base === '') {
            $this->error('No base url: set APP_URL or pass --url.');
            return self::FAILURE;
        }

        $this->info("Testing {$base} as a logged-out client.");

        // Sample across every source: they carry different fields, and a leak
        // in one source's mapping would hide behind another's blanks.
        $all = $feed->getAllProperties();
        $perSource = max(1, (int) ceil(((int) $this->option('listings')) / max(1, $all->groupBy('source')->count())));

        $sample = $all->groupBy(fn ($p) => $p['source'] ?? 'unknown')
            ->map(fn ($rows) => $rows->filter(fn ($p) => ! empty($p['agent_name']))->take($perSource))
            ->flatten(1);

        if ($sample->isEmpty()) {
            $this->warn('No listings to sample.');
            return self::SUCCESS;
        }

        $problems = 0;

        foreach ($sample as $property) {
            $id = (string) ($property['id'] ?? '');

            if ($id === '') {
                continue;
            }

            try {
                $response = Http::timeout(30)->get("{$base}/properties/{$id}");
            } catch (\Throwable $e) {
                $this->line("  <fg=yellow>?</> {$id}: unreachable ({$e->getMessage()})");
                continue;
            }

            if (! $response->successful()) {
                $this->line("  <fg=yellow>?</> {$id}: HTTP {$response->status()}");
                continue;
            }

            $html = $response->body();
            $found = [];

            foreach ([
                'the agency name' => $property['agent_name'] ?? null,
                'the landlord name' => $property['landlord_name'] ?? null,
                'the source advert' => $property['link'] ?? null,
            ] as $label => $value) {
                if ($this->appears($html, $value)) {
                    $found[] = $label;
                }
            }

            // Wording that tells a client something is being kept from them.
            foreach ([
                'a "registered users" notice' => '/available to registered|contact your agent for more/i',
                'the paying-agent marker' => '/paying agent/i',
                'a landlord portal link' => '#go/landlord#i',
                'an original-listing link' => '/View Original Listing/i',
            ] as $label => $pattern) {
                if (preg_match($pattern, $html)) {
                    $found[] = $label;
                }
            }

            if ($found) {
                $problems++;
                $this->line(sprintf(
                    '  <fg=red>x</> %-16s (%s) exposes: %s',
                    mb_substr($id, 0, 16),
                    $property['source'] ?? '?',
                    implode(', ', $found)
                ));
                continue;
            }

            $this->line(sprintf('  <fg=green>v</> %-16s (%s) clean', mb_substr($id, 0, 16), $property['source'] ?? '?'));
        }

        $this->newLine();

        if ($problems === 0) {
            $this->info('Every sampled link is safe to send a client.');
            return self::SUCCESS;
        }

        $this->error("{$problems} listing page(s) expose agent-side information.");

        return self::FAILURE;
    }

    /**
     * Does a value actually appear as itself? A short name needs word
     * boundaries: "AP" is inside "Wapping" and every "apartment".
     */
    protected function appears(string $html, $value): bool
    {
        $value = trim((string) $value);

        if ($value === '' || strcasecmp($value, 'N/A') === 0) {
            return false;
        }

        if (mb_strlen($value) < 4) {
            return (bool) preg_match('/(?<![A-Za-z])' . preg_quote($value, '/') . '(?![A-Za-z])/i', $html);
        }

        return stripos($html, $value) !== false;
    }
}
