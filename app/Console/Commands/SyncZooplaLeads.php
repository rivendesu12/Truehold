<?php

namespace App\Console\Commands;

use App\Services\ZooplaLeadParser;
use App\Services\ZooplaLeadsSheet;
use App\Support\ImapInbox;
use App\Support\ZooplaLeadsSettings;
use Illuminate\Console\Command;
use Throwable;

/**
 * Reads "Tenant enquiry from X via Zoopla" emails from the Joy Homes Zoho
 * mailbox and adds the people to the Zoopla leads sheet.
 *
 * Read-only on the mailbox (nothing is marked read). Looks back a few days
 * every run and skips what the sheet already has, so a missed or failed run
 * is picked up by the next one.
 */
class SyncZooplaLeads extends Command
{
    protected $signature = 'zoopla:leads
        {--days=3 : How many days of email to look through}
        {--dry-run : Show the leads found, write nothing}';

    protected $description = 'Copy Zoopla tenant enquiries from Zoho Mail into the leads sheet';

    public function handle(ZooplaLeadsSheet $sheet): int
    {
        $config = ['password' => ZooplaLeadsSettings::password()] + config('services.zoopla_leads');

        if (blank($config['username']) || blank($config['password'])) {
            $this->warn('No Zoho password yet: enter it on /admin/zoopla-leads.');

            return self::SUCCESS;
        }

        try {
            $leads = $this->readLeads($config, max(1, (int) $this->option('days')));
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->record(false, str_contains($e->getMessage(), 'LOGIN')
                ? 'Zoho refused the password (or IMAP is off in Zoho).'
                : 'Could not read the mailbox: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info(count($leads) . ' Zoopla ' . str('enquiry')->plural(count($leads)) . ' in the last ' . $this->option('days') . ' days.');

        if ($this->option('dry-run')) {
            $this->table(
                ['Received', 'Name', 'Phone', 'Email', 'Area', 'Price', 'Link'],
                array_map(fn ($l) => [
                    $l['received_at']->format('d/m H:i'), $l['name'], $l['phone'], $l['email'], $l['area'], $l['price'], $l['property_link'],
                ], $leads)
            );

            return self::SUCCESS;
        }

        if (! $sheet->configured()) {
            $this->warn('ZOOPLA_LEADS_SHEET_ID or the service-account credentials are not set; nothing written.');
            $this->record(false, 'Mailbox read fine, but the sheet is not configured on the server.');

            return self::FAILURE;
        }

        try {
            $sheet->ensureTabs();
            $added = $sheet->append($leads);
        } catch (Throwable $e) {
            $this->error('Sheet write failed: ' . $e->getMessage());
            $shareWith = $sheet->serviceAccountEmail();
            if ($shareWith) {
                $this->line("Is the sheet shared with {$shareWith} as Editor?");
            }
            $this->record(false, 'Mailbox read fine, but the sheet refused the write'
                . ($shareWith ? " — share it with {$shareWith} as Editor." : '.'));

            return self::FAILURE;
        }

        foreach ($added as $tab => $count) {
            $this->line("  {$tab}: +{$count}");
        }
        $this->record(true, count($leads) . ' Zoopla ' . str('enquiry')->plural(count($leads))
            . ' in the last ' . $this->option('days') . ' days; ' . $added[ZooplaLeadsSheet::TO_MESSAGE] . ' new ' . str('person')->plural($added[ZooplaLeadsSheet::TO_MESSAGE]) . ' added.');

        return self::SUCCESS;
    }

    /** Shown on /admin/zoopla-leads. Dry runs change nothing. */
    private function record(bool $ok, string $message): void
    {
        if (! $this->option('dry-run')) {
            ZooplaLeadsSettings::recordRun(['ok' => $ok, 'message' => $message]);
        }
    }

    /** @return array<int, array<string, mixed>> oldest first */
    private function readLeads(array $config, int $days): array
    {
        $inbox = new ImapInbox($config['imap_host'], (int) $config['imap_port']);
        $inbox->login($config['username'], $config['password']);

        try {
            $inbox->examine($config['folder']);
            $since = now()->subDays($days)->format('j-M-Y');
            // Subject is filtered after decoding: Zoopla sends it base64-encoded.
            $uids = $inbox->search('SINCE ' . $since . ' FROM "members@zoopla.co.uk"');

            $leads = [];
            foreach ($uids as $uid) {
                $raw = $inbox->fetchRaw($uid);
                if ($raw && ($lead = ZooplaLeadParser::parse($raw))) {
                    $leads[] = $lead;
                }
            }
        } finally {
            $inbox->logout();
        }

        usort($leads, fn ($a, $b) => $a['received_at'] <=> $b['received_at']);

        return $leads;
    }
}
