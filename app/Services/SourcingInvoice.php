<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * The sourcing-fee invoice the office used to make on invoice-generator.com,
 * made here and kept in the admin's invoices table so every one is on record
 * and can be marked paid there.
 *
 * Numbering carries on the office's own sequence (#5165 onwards), plain
 * numbers, so it never collides with the admin's "INV-000001" style.
 */
class SourcingInvoice
{
    public const FIRST_NUMBER = 5165;
    public const COMPANY = 'Truehold Group Ltd';
    public const ADDRESS = '154 Bishopsgate, EC2M 4LN';
    public const ITEM = 'Sourcing agreement';

    /**
     * Make (or, on a repeat click, reuse) the invoice.
     *
     * The same client, amount and date from the same agent within ten minutes
     * is the same invoice: a double tap must not burn a second number.
     */
    public function create(string $client, float $amount, Carbon $date, ?string $agent = null, bool $paid = true): Invoice
    {
        $client = trim(preg_replace('/\s+/', ' ', $client));

        $recent = Invoice::query()
            ->where('client_name', $client)
            ->where('total_amount', $amount)
            ->whereDate('invoice_date', $date->toDateString())
            ->where('created_at', '>=', now()->subMinutes(10))
            ->whereRaw("invoice_number NOT LIKE 'INV-%'")
            ->latest('id')
            ->first();
        if ($recent) {
            return $recent;
        }

        $fields = [
            'invoice_date' => $date->toDateString(),
            'due_date' => $date->toDateString(),
            'payment_terms' => '',
            'company_name' => self::COMPANY,
            'company_address' => self::ADDRESS,
            // Not on the office's invoice, but the table requires them.
            'account_holder_name' => '',
            'account_number' => '',
            'sort_code' => '',
            'client_name' => $client,
            'client_address' => '',
            'items' => [['description' => self::ITEM, 'quantity' => 1, 'rate' => $amount, 'amount' => $amount]],
            'subtotal' => $amount,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total_amount' => $amount,
            'amount_paid' => $paid ? $amount : 0,
            'balance_due' => $paid ? 0 : $amount,
            'status' => $paid ? 'paid' : 'sent',
        ];
        if ($agent && Schema::hasColumn('invoices', 'agent_name')) {
            $fields['agent_name'] = $agent;
        }

        // Two agents at the same moment can reach for the same number; the
        // unique index refuses the second, which simply takes the next.
        for ($try = 0; $try < 5; $try++) {
            try {
                return Invoice::create(['invoice_number' => (string) $this->nextNumber()] + $fields);
            } catch (QueryException $e) {
                if ($try === 4 || ! str_contains(strtolower($e->getMessage()), 'unique')) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Could not number the invoice.');
    }

    public function nextNumber(): int
    {
        $highest = Invoice::query()
            ->pluck('invoice_number')
            ->filter(fn ($n) => ctype_digit((string) $n))
            ->map(fn ($n) => (int) $n)
            ->max();

        return max(self::FIRST_NUMBER, ($highest ?? 0) + 1);
    }

    /** @return string the PDF bytes */
    public function pdf(Invoice $invoice): string
    {
        $logo = 'data:image/png;base64,' . base64_encode(file_get_contents(resource_path('agreements/truehold-logo.png')));

        return Pdf::loadView('invoices.sourcing', compact('invoice', 'logo'))
            ->setPaper('a4')
            ->output();
    }

    public static function filename(Invoice $invoice): string
    {
        return 'Invoice ' . $invoice->invoice_number . ' - '
            . trim(preg_replace('/[^A-Za-z0-9]+/', ' ', \Illuminate\Support\Str::ascii($invoice->client_name))) . '.pdf';
    }
}
