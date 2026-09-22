<?php

use App\Models\Invoice;
use App\Models\User;
use App\Services\AgentSearchAssistant;
use App\Services\ScrapedListingsApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fakeInvoiceAssistant(array &$seen, array $replies): void
{
    $fake = Mockery::mock(AgentSearchAssistant::class)->makePartial();
    $fake->shouldReceive('isConfigured')->andReturn(true);
    $fake->shouldReceive('parse')->andReturnUsing(function ($q, $l, $prev = null, $pendingAgreement = null, $pendingInvoice = null) use (&$seen, $replies) {
        $seen[] = $pendingInvoice;

        return ['sigou' => 'ok', 'invoice' => ($replies[$q] ?? []) + [
            'wanted' => true, 'client_name' => null, 'amount' => null, 'date' => null, 'paid' => true,
        ]];
    });
    app()->instance(AgentSearchAssistant::class, $fake);

    $feed = Mockery::mock(ScrapedListingsApiService::class);
    $feed->shouldReceive('getAllProperties')->andReturn(collect());
    app()->instance(ScrapedListingsApiService::class, $feed);
}

it('numbers invoices from 5165, keeps them on record, and returns the PDF', function () {
    $this->actingAs(User::factory()->create(['name' => 'giacomo chaparro']));

    $first = $this->post('/tools/invoice/pdf', ['client_name' => 'Alexander Marcano', 'amount' => 250, 'date' => '2026-09-22']);
    $first->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($first->headers->get('Content-Disposition'))->toContain('Invoice 5165 - Alexander Marcano.pdf');

    $this->post('/tools/invoice/pdf', ['client_name' => 'Anna Nowak', 'amount' => 220])->assertOk();

    $invoices = Invoice::orderBy('id')->get();
    expect($invoices->pluck('invoice_number')->all())->toBe(['5165', '5166']);
    expect($invoices[0]->status)->toBe('paid');
    expect((float) $invoices[0]->balance_due)->toBe(0.0);
    expect($invoices[0]->items[0]['description'])->toBe('Sourcing agreement');
});

it('does not burn a second number on a double click', function () {
    $this->actingAs(User::factory()->create());

    $this->post('/tools/invoice/pdf', ['client_name' => 'Maria Lopez', 'amount' => 250, 'date' => '2026-09-22'])->assertOk();
    $this->post('/tools/invoice/pdf', ['client_name' => 'Maria Lopez', 'amount' => 250, 'date' => '2026-09-22'])->assertOk();

    expect(Invoice::count())->toBe(1);
});

it('records an unpaid invoice with the balance due', function () {
    $this->actingAs(User::factory()->create());

    $this->post('/tools/invoice/pdf', ['client_name' => 'Maria Lopez', 'amount' => 250, 'paid' => '0'])->assertOk();

    $invoice = Invoice::first();
    expect($invoice->status)->toBe('sent');
    expect((float) $invoice->balance_due)->toBe(250.0);
});

it('takes the fee from the agreement card for "Invoice too"', function () {
    $this->actingAs(User::factory()->create());

    $this->post('/tools/invoice/pdf', ['client_name' => 'Maria Lopez', 'fee' => 220, 'date' => '2026-09-22'])->assertOk();

    expect((float) Invoice::first()->total_amount)->toBe(220.0);
});

it('keeps admin-style numbers out of the sequence', function () {
    Invoice::unguarded(fn () => Invoice::forceCreate([
        'invoice_number' => 'INV-000900', 'invoice_date' => now(), 'due_date' => now(), 'company_name' => 'x',
        'company_address' => 'x', 'account_holder_name' => '', 'account_number' => '', 'sort_code' => '',
        'client_name' => 'x', 'client_address' => '', 'items' => [], 'subtotal' => 1, 'total_amount' => 1, 'balance_due' => 1,
    ]));
    $this->actingAs(User::factory()->create());

    $this->post('/tools/invoice/pdf', ['client_name' => 'Maria Lopez', 'amount' => 250])->assertOk();

    expect(Invoice::latest('id')->first()->invoice_number)->toBe('5165');
});

it('asks for what is missing, then completes it from the answer', function () {
    $seen = [];
    fakeInvoiceAssistant($seen, [
        'malaka make an invoice' => [],
        'Alexander Marcano, cash' => ['client_name' => 'Alexander Marcano', 'amount' => 220],
    ]);
    $this->actingAs(User::factory()->create());

    $this->postJson('/agent-search', ['q' => 'malaka make an invoice'])
        ->assertOk()
        ->assertJsonPath('invoice.ready', false)
        ->assertJsonPath('invoice.missing', ['client_name', 'amount'])
        ->assertJsonPath('invoice.next_number', 5165);

    $this->postJson('/agent-search', ['q' => 'Alexander Marcano, cash'])
        ->assertOk()
        ->assertJsonPath('invoice.ready', true)
        ->assertJsonPath('invoice.client_name', 'Alexander Marcano')
        ->assertJsonPath('invoice.amount', 220);

    expect($seen[0])->toBeNull();
    expect($seen[1])->toMatchArray(['client_name' => null, 'amount' => null]);
    // Asking makes nothing: an invoice only exists once downloaded.
    expect(Invoice::count())->toBe(0);
});

it('is for signed-in agents only', function () {
    $this->post('/tools/invoice/pdf', ['client_name' => 'A', 'amount' => 250])->assertRedirect();
    expect(Invoice::count())->toBe(0);
});
