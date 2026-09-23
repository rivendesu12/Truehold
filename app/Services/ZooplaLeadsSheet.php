<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Sheets as GoogleSheets;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\ValueRange;

/**
 * The Zoopla leads spreadsheet, laid out like the old "New Zoopla leads" one:
 *
 *  - Sheet1: every enquiry, as it arrived.
 *  - To message: one row per new phone number, with a Messaged column to tick.
 *  - Message template: the same people in the CRM import format.
 *
 * Rows are only ever appended, never rewritten, so anything typed into the
 * sheet by hand stays put. Written as the app's service account, which needs
 * Editor on the sheet.
 */
class ZooplaLeadsSheet
{
    public const LEADS = 'Sheet1';
    public const TO_MESSAGE = 'To message';
    public const TEMPLATE = 'Message template';

    public const HEADERS = [
        self::LEADS => ['Name', 'Phone', 'Date', 'Source', 'Property Link', 'Area', 'Price', 'Reference', 'Status', 'Full Address', 'Email', 'Zoopla Ref'],
        self::TO_MESSAGE => ['Name', 'Phone', 'Date', 'Source', 'Property Link', 'Area', 'Price', 'Reference', 'Status', 'Full Address', 'Messaged'],
        self::TEMPLATE => ['phone', 'name', 'surname', 'email', 'nationality', 'visaStatus', 'monthlyIncome', 'budgetMin', 'budgetMax', 'age', 'preferredAreas', 'moveInDate', 'occupation', 'source', 'referredByColleague', 'transactionType', 'preferredCategory', 'incomeProofUrl', 'idDocumentUrl'],
    ];

    private ?GoogleSheets $sheets = null;

    public function __construct(
        private ?string $spreadsheetId = null,
        private ?string $credentialsPath = null,
    ) {
        $this->spreadsheetId ??= config('services.zoopla_leads.spreadsheet_id');
        $this->credentialsPath ??= config('services.zoopla_leads.credentials_path');
    }

    public function configured(): bool
    {
        return filled($this->spreadsheetId) && $this->credentialsPath && is_file($this->credentialsPath);
    }

    /** The address the sheet has to be shared with. */
    public function serviceAccountEmail(): ?string
    {
        if (! $this->credentialsPath || ! is_file($this->credentialsPath)) {
            return null;
        }

        return json_decode((string) file_get_contents($this->credentialsPath), true)['client_email'] ?? null;
    }

    /** Creates any missing tab and writes headers into an empty first row. */
    public function ensureTabs(): void
    {
        $existing = collect($this->sheets()->spreadsheets->get($this->spreadsheetId)->getSheets())
            ->map(fn ($s) => $s->getProperties()->getTitle())->all();

        $missing = array_diff(array_keys(self::HEADERS), $existing);
        if ($missing) {
            $this->sheets()->spreadsheets->batchUpdate($this->spreadsheetId, new BatchUpdateSpreadsheetRequest([
                'requests' => array_map(fn ($title) => ['addSheet' => ['properties' => ['title' => $title]]], array_values($missing)),
            ]));
        }

        foreach (self::HEADERS as $tab => $headers) {
            $first = $this->sheets()->spreadsheets_values->get($this->spreadsheetId, $this->range($tab, '1:1'))->getValues();
            if (empty($first[0] ?? [])) {
                $this->sheets()->spreadsheets_values->update(
                    $this->spreadsheetId, $this->range($tab, 'A1'),
                    new ValueRange(['values' => [$headers]]), ['valueInputOption' => 'RAW']
                );
            }
        }
    }

    /**
     * Appends leads not already in the sheet. Sheet1 takes every new enquiry;
     * the other two take a person once, by phone number (or email if none).
     *
     * @param  array<int, array<string, mixed>>  $leads  from ZooplaLeadParser, oldest first
     * @return array<string, int> rows added per tab
     */
    public function append(array $leads): array
    {
        $logged = collect($this->column(self::LEADS, 'A2:L'))
            ->map(fn ($r) => self::enquiryKey(ZooplaLeadParser::internationalPhone($r[1] ?? '') ?? strtolower($r[10] ?? ''), $r[2] ?? '', $r[4] ?? ''))
            ->flip();
        $toMessage = collect($this->column(self::TO_MESSAGE, 'B2:B'))
            ->map(fn ($r) => ZooplaLeadParser::internationalPhone($r[0] ?? ''))->filter()->flip();
        $template = collect($this->column(self::TEMPLATE, 'A2:D'))
            ->flatMap(fn ($r) => array_filter([ZooplaLeadParser::internationalPhone($r[0] ?? ''), strtolower($r[3] ?? '')]))->flip();

        $rows = [self::LEADS => [], self::TO_MESSAGE => [], self::TEMPLATE => []];

        foreach ($leads as $lead) {
            $person = $lead['phone_intl'] ?? $lead['email'];
            $key = self::enquiryKey($person, $lead['received_at']->format('Y-m-d H:i'), $lead['property_link'] ?? '');

            if (! $logged->has($key)) {
                $logged->put($key, true);
                $rows[self::LEADS][] = $this->leadRow($lead);
            }
            if (! $toMessage->has($person)) {
                $toMessage->put($person, true);
                $rows[self::TO_MESSAGE][] = $this->toMessageRow($lead);
            }
            if (! $template->has($person) && ! $template->has($lead['email'] ?? '')) {
                $template->put($person, true);
                $rows[self::TEMPLATE][] = $this->templateRow($lead);
            }
        }

        foreach ($rows as $tab => $values) {
            if ($values) {
                $this->sheets()->spreadsheets_values->append(
                    $this->spreadsheetId, $this->range($tab, 'A1'),
                    new ValueRange(['values' => $values]),
                    ['valueInputOption' => 'RAW', 'insertDataOption' => 'INSERT_ROWS']
                );
            }
        }

        return array_map('count', $rows);
    }

    private static function enquiryKey(?string $person, string $date, string $link): string
    {
        return ($person ?? '') . '|' . $date . '|' . rtrim(str_replace('https://', 'http://', $link), '/');
    }

    private function leadRow(array $l): array
    {
        return [
            $l['name'], $l['phone'] ?? '', $l['received_at']->format('Y-m-d H:i'), 'Zoopla',
            $l['property_link'] ?? '', $l['area'] ?? '', $l['price'] ?? '', $l['property_ref'] ?? '',
            'new', $l['address'] ?? '', $l['email'] ?? '', $l['listing_ref'] ?? '',
        ];
    }

    private function toMessageRow(array $l): array
    {
        return [
            $l['name'], $l['phone'] ?? '', $l['received_at']->format('d/m/Y'), 'Zoopla',
            $l['property_link'] ?? '', $l['area'] ?? '', $l['price'] ?? '', $l['property_ref'] ?? '',
            'new', $l['address'] ?? '', '',
        ];
    }

    private function templateRow(array $l): array
    {
        $row = array_fill_keys(self::HEADERS[self::TEMPLATE], '');

        return array_values(array_merge($row, [
            'phone' => $l['phone_intl'] ?? '',
            'name' => $l['first_name'],
            'surname' => $l['surname'],
            'email' => $l['email'] ?? '',
            'budgetMax' => $l['price'] ?? '',
            'preferredAreas' => $l['area'] ?? '',
            'source' => 'Zoopla',
        ]));
    }

    private function column(string $tab, string $range): array
    {
        return $this->sheets()->spreadsheets_values->get($this->spreadsheetId, $this->range($tab, $range))->getValues() ?? [];
    }

    private function range(string $tab, string $cells): string
    {
        return "'" . str_replace("'", "''", $tab) . "'!" . $cells;
    }

    private function sheets(): GoogleSheets
    {
        if (! $this->sheets) {
            $client = new GoogleClient();
            $client->setScopes([GoogleSheets::SPREADSHEETS]);
            $client->setAuthConfig($this->credentialsPath);
            $this->sheets = new GoogleSheets($client);
        }

        return $this->sheets;
    }
}
