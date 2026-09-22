<?php

use App\Models\User;
use App\Services\AgentSearchAssistant;
use App\Services\ScrapedListingsApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * The follow-up memory lives in the endpoint, not the model: "max 650" only
 * refines the last search if the server hands that search back. The model is
 * faked here; assistant:test covers what it does with the previous filters.
 */

function fakeAssistant(array &$seen): void
{
    $fake = Mockery::mock(AgentSearchAssistant::class)->makePartial();
    $fake->shouldReceive('isConfigured')->andReturn(true);
    $fake->shouldReceive('parse')->andReturnUsing(function ($q, $locations, $previous = null) use (&$seen) {
        $seen[] = $previous;

        return match ($q) {
            'hey sigou' => ['chit_chat' => true, 'sigou' => 'Ela bro'],
            'east london up to zone 3' => ['region' => 'east', 'max_zone' => 3, 'garden' => false, 'sigou' => 'ok'],
            default => ['region' => 'east', 'max_zone' => 3, 'max_price' => 650, 'refines_previous' => $previous !== null],
        };
    });
    $fake->shouldReceive('search')->andReturn([
        'matched' => 0, 'radius' => null, 'hub' => null, 'hub_label' => null,
        'commission' => collect(), 'standard' => collect(), 'alternatives' => collect(),
    ]);
    app()->instance(AgentSearchAssistant::class, $fake);

    $feed = Mockery::mock(ScrapedListingsApiService::class);
    $feed->shouldReceive('getAllProperties')->andReturn(collect());
    app()->instance(ScrapedListingsApiService::class, $feed);
}

it('hands the last search to a follow-up and marks it refined', function () {
    $seen = [];
    fakeAssistant($seen);
    $this->actingAs(User::factory()->create());

    $this->postJson('/agent-search', ['q' => 'east london up to zone 3'])->assertOk()->assertJson(['refined' => false]);
    $this->postJson('/agent-search', ['q' => 'max 650'])->assertOk()->assertJson(['refined' => true]);

    expect($seen[0])->toBeNull();
    // Only what was actually set travels: garden=false and Sigou's line do not.
    expect($seen[1])->toBe(['region' => 'east', 'max_zone' => 3]);
});

it('starts fresh after New search', function () {
    $seen = [];
    fakeAssistant($seen);
    $this->actingAs(User::factory()->create());

    $this->postJson('/agent-search', ['q' => 'east london up to zone 3'])->assertOk();
    $this->postJson('/agent-search', ['q' => 'max 650', 'fresh' => true])->assertOk()->assertJson(['refined' => false]);

    expect($seen[1])->toBeNull();
});

it('forgets the last search after 30 minutes', function () {
    $seen = [];
    fakeAssistant($seen);
    $this->actingAs(User::factory()->create());

    $this->postJson('/agent-search', ['q' => 'east london up to zone 3'])->assertOk();
    $this->travel(31)->minutes();
    $this->postJson('/agent-search', ['q' => 'max 650'])->assertOk();

    expect($seen[1])->toBeNull();
});

it('leaves the search alone when the agent is only chatting', function () {
    $seen = [];
    fakeAssistant($seen);
    $this->actingAs(User::factory()->create());

    $this->postJson('/agent-search', ['q' => 'east london up to zone 3'])->assertOk();
    $this->postJson('/agent-search', ['q' => 'hey sigou'])->assertOk()->assertJson(['chat' => true]);
    $this->postJson('/agent-search', ['q' => 'max 650'])->assertOk()->assertJson(['refined' => true]);

    expect($seen[2])->toBe(['region' => 'east', 'max_zone' => 3]);
});

it('keeps each agent\'s search to themselves', function () {
    $seen = [];
    fakeAssistant($seen);

    $this->actingAs(User::factory()->create());
    $this->postJson('/agent-search', ['q' => 'east london up to zone 3'])->assertOk();

    // A different agent, in a fresh session, has no previous search.
    $this->flushSession();
    $this->actingAs(User::factory()->create());
    $this->postJson('/agent-search', ['q' => 'max 650'])->assertOk()->assertJson(['refined' => false]);

    expect($seen[1])->toBeNull();
});
