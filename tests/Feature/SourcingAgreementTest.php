<?php

use App\Models\User;
use App\Services\AgentSearchAssistant;
use App\Services\ScrapedListingsApiService;
use App\Services\SourcingAgreement;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The model is faked: each message maps to what it would return. What is
 * tested is the server: filling the gaps across messages, who signs, and the
 * PDF itself.
 */
function fakeAgreementAssistant(array &$seen, array $replies): void
{
    $fake = Mockery::mock(AgentSearchAssistant::class)->makePartial();
    $fake->shouldReceive('isConfigured')->andReturn(true);
    $fake->shouldReceive('parse')->andReturnUsing(function ($q, $l, $previous = null, $pending = null) use (&$seen, $replies) {
        $seen[] = $pending;
        $a = $replies[$q] ?? [];

        return ['sigou' => 'ok', 'agreement' => $a + [
            'wanted' => true, 'template_only' => false, 'client_name' => null, 'fee' => null, 'date' => null, 'sign_as' => null,
        ]];
    });
    app()->instance(AgentSearchAssistant::class, $fake);

    $feed = Mockery::mock(ScrapedListingsApiService::class);
    $feed->shouldReceive('getAllProperties')->andReturn(collect());
    app()->instance(ScrapedListingsApiService::class, $feed);
}

it('is for signed-in agents only', function () {
    $this->get('/tools/sourcing-agreement/template')->assertRedirect();
    $this->post('/tools/sourcing-agreement/pdf', ['client_name' => 'A', 'fee' => 250])->assertRedirect();
});

it('fills in the agreement and returns a PDF signed by the agent', function () {
    $this->actingAs(User::factory()->create(['name' => 'giacomo chaparro']));

    $response = $this->post('/tools/sourcing-agreement/pdf', [
        'client_name' => 'Maria Lopez', 'fee' => 250, 'date' => '2026-09-22', 'sign_as' => 'Giacomo',
    ]);

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('Sourcing Agreement - Maria Lopez.pdf');
    expect(substr($response->getContent(), 0, 5))->toBe('%PDF-');
});

it('will not make an agreement without a name and a fee', function () {
    $this->actingAs(User::factory()->create());

    $this->post('/tools/sourcing-agreement/pdf', ['fee' => 250, 'sign_as' => 'Giacomo'])->assertSessionHasErrors('client_name');
    $this->post('/tools/sourcing-agreement/pdf', ['client_name' => 'Maria Lopez', 'sign_as' => 'Giacomo'])->assertSessionHasErrors('fee');
    // Agents share a login, so the signer is never taken from it.
    $this->post('/tools/sourcing-agreement/pdf', ['client_name' => 'Maria Lopez', 'fee' => 250])->assertSessionHasErrors('sign_as');
});

it('serves the blank template', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get('/tools/sourcing-agreement/template')->assertOk();
    expect($response->getContent())->toBe(file_get_contents(SourcingAgreement::templatePath()));
});

it('asks for what is missing, then completes it from the answer', function () {
    $seen = [];
    fakeAgreementAssistant($seen, [
        'make me a sourcing agreement' => [],
        'Maria Lopez, transfer' => ['client_name' => 'Maria Lopez', 'fee' => 250],
    ]);
    // The shared office login: its name must never end up as the signature.
    $this->actingAs(User::factory()->create(['name' => 'Agent']));

    $this->postJson('/agent-search', ['q' => 'make me a sourcing agreement'])
        ->assertOk()
        ->assertJsonPath('agreement.ready', false)
        ->assertJsonPath('agreement.missing', ['client_name', 'fee', 'sign_as'])
        ->assertJsonPath('agreement.sign_as', null);

    $this->postJson('/agent-search', ['q' => 'Maria Lopez, transfer'])
        ->assertOk()
        ->assertJsonPath('agreement.ready', false)
        ->assertJsonPath('agreement.missing', ['sign_as'])
        ->assertJsonPath('sigou', 'All good, just who signs? Put your name bro')
        ->assertJsonPath('agreement.client_name', 'Maria Lopez')
        ->assertJsonPath('agreement.fee', 250)
        ->assertJsonPath('agreement.referral', 50)
        ->assertJsonPath('agreement.date', now()->toDateString());

    // The second message was sent with what the first one collected.
    expect($seen[0])->toBeNull();
    expect($seen[1])->toMatchArray(['client_name' => null, 'fee' => null, 'sign_as' => null]);
});

it('remembers who signed on this device for the next agreement', function () {
    $seen = [];
    fakeAgreementAssistant($seen, ['agreement for Anna Nowak 250' => ['client_name' => 'Anna Nowak', 'fee' => 250]]);
    $this->actingAs(User::factory()->create(['name' => 'Agent']));

    $this->post('/tools/sourcing-agreement/pdf', ['client_name' => 'Maria Lopez', 'fee' => 250, 'sign_as' => 'Emanuela'])->assertOk();

    $this->postJson('/agent-search', ['q' => 'agreement for Anna Nowak 250'])
        ->assertJsonPath('agreement.sign_as', 'Emanuela')
        ->assertJsonPath('agreement.ready', true);
});

it('signs as another agent when asked', function () {
    $seen = [];
    fakeAgreementAssistant($seen, ['agreement for Anna Nowak 220, sign as alex' => ['client_name' => 'Anna Nowak', 'fee' => 220, 'sign_as' => 'Alex']]);
    $this->actingAs(User::factory()->create(['name' => 'giacomo chaparro']));

    $this->postJson('/agent-search', ['q' => 'agreement for Anna Nowak 220, sign as alex'])
        ->assertJsonPath('agreement.sign_as', 'Alex')
        ->assertJsonPath('agreement.ready', true);
});

it('hands over the blank template when that is all they want', function () {
    $seen = [];
    fakeAgreementAssistant($seen, ['blank agreement pls' => ['template_only' => true]]);
    $this->actingAs(User::factory()->create());

    $this->postJson('/agent-search', ['q' => 'blank agreement pls'])
        ->assertOk()
        ->assertJsonPath('agreement.template', true)
        ->assertJsonPath('agreement.template_url', route('agreement.template'));
});
