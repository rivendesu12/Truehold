{{-- The sourcing-fee invoice, laid out like the ones the office made on
     invoice-generator.com. Rendered by dompdf, so plain CSS 2 and tables. --}}
@php
    $gbp = fn ($n) => '£' . number_format((float) $n, 2);
    $item = $invoice->items[0] ?? ['description' => 'Sourcing agreement', 'quantity' => 1, 'rate' => $invoice->total_amount];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice {{ $invoice->invoice_number }}</title>
<style>
    @page { margin: 34px 40px; }
    body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; color: #333; font-size: 12px; }
    table { border-collapse: collapse; width: 100%; }
    .top td { vertical-align: top; }
    .logo { width: 128px; height: 128px; border-radius: 4px; }
    .title { text-align: right; font-size: 34px; letter-spacing: 1px; color: #333; font-weight: normal; margin: 0; }
    .number { text-align: right; font-size: 15px; color: #777; margin-top: 2px; }
    .meta { margin-top: 26px; }
    .meta td { padding: 5px 12px; font-size: 12px; }
    .meta .k { text-align: right; color: #777; }
    .meta .v { text-align: right; width: 120px; }
    .due td { background: #f4f4f4; font-weight: bold; font-size: 14px; color: #333; }
    .from { margin-top: 22px; line-height: 1.45; }
    .from b { font-size: 12.5px; }
    .billto { margin-top: 18px; }
    .billto .k { color: #777; }
    .billto b { display: block; margin-top: 4px; font-size: 12.5px; }
    .items { margin-top: 44px; }
    .items th { background: #3a3a3a; color: #fff; font-weight: normal; padding: 7px 14px; text-align: left; font-size: 12px; }
    .items th.n, .items td.n { text-align: right; }
    .items td { padding: 10px 14px; font-size: 12px; }
    .items td.item { font-weight: bold; }
    .totals { margin-top: 44px; }
    .totals td { padding: 6px 14px; font-size: 12px; }
    .totals .k { text-align: right; color: #777; }
    .totals .v { text-align: right; width: 120px; }
</style>
</head>
<body>

<table class="top">
    <tr>
        <td><img class="logo" src="{{ $logo }}" alt="Truehold Group"></td>
        <td>
            <p class="title">INVOICE</p>
            <div class="number"># {{ $invoice->invoice_number }}</div>

            <table class="meta">
                <tr><td class="k">Date:</td><td class="v">{{ $invoice->invoice_date->format('M j, Y') }}</td></tr>
                <tr class="due"><td class="k" style="color:#333">Balance Due:</td><td class="v">{{ $gbp($invoice->balance_due) }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<div class="from">
    <b>{{ $invoice->company_name }}</b><br>
    {{ $invoice->company_address }}
</div>

<div class="billto">
    <span class="k">Bill To:</span>
    <b>{{ $invoice->client_name }}</b>
</div>

<table class="items">
    <thead>
        <tr><th>Item</th><th class="n" style="width:80px">Quantity</th><th class="n" style="width:90px">Rate</th><th class="n" style="width:90px">Amount</th></tr>
    </thead>
    <tbody>
        <tr>
            <td class="item">{{ $item['description'] }}</td>
            <td class="n">{{ $item['quantity'] }}</td>
            <td class="n">{{ $gbp($item['rate']) }}</td>
            <td class="n">{{ $gbp($item['quantity'] * $item['rate']) }}</td>
        </tr>
    </tbody>
</table>

<table class="totals">
    <tr><td class="k">Subtotal:</td><td class="v">{{ $gbp($invoice->subtotal) }}</td></tr>
    <tr><td class="k">Tax ({{ rtrim(rtrim(number_format((float) $invoice->tax_rate, 2), '0'), '.') }}%):</td><td class="v">{{ $gbp($invoice->tax_amount) }}</td></tr>
    <tr><td class="k">Total:</td><td class="v">{{ $gbp($invoice->total_amount) }}</td></tr>
    <tr><td class="k">Amount Paid:</td><td class="v">{{ $gbp($invoice->amount_paid) }}</td></tr>
</table>

</body>
</html>
