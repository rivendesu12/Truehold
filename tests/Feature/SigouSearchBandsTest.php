<?php

use App\Models\User;
use App\Services\AgencyDirectory;
use App\Services\AgentSearchAssistant;
use App\Services\CommissionRates;
use App\Services\MarketListingsService;
use App\Services\ScrapedListingsApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
 * The model is faked (it returns a fixed spec); the search, the bands, the
 * wildcards, the log and the agency answers are the real code.
 */

function room(string $id, array $o = []): array
{
    return $o + [
        'id' => $id, 'title' => 'Room ' . $id, 'price' => 800, 'location' => 'Somewhere',
        'latitude' => 51.5, 'longitude' => -0.1, 'agent_name' => 'Some Agency', 'paying' => 'no',
        'property_type' => 'Room', 'room_type' => 'double',
    ];
}

function fakeSigou(array $spec, array $feed): void
{
    $fake = Mockery::mock(AgentSearchAssistant::class)->makePartial();
    $fake->shouldReceive('isConfigured')->andReturn(true);
    $fake->shouldReceive('parse')->andReturn($spec + ['sigou' => 'ok', 'sigou_found' => 'found', 'sigou_none' => 'none']);
    app()->instance(AgentSearchAssistant::class, $fake);

    $service = Mockery::mock(ScrapedListingsApiService::class);
    $service->shouldReceive('getAllProperties')->andReturn(collect($feed));
    app()->instance(ScrapedListingsApiService::class, $service);
}

// Canary Wharf station 51.5048, -0.0210; Mile End 51.5251, -0.0332.
function canaryWharfFeed(): array
{
    return [
        room('cw', ['location' => 'Canary Wharf', 'latitude' => 51.5050, 'longitude' => -0.0215, 'nearest_station' => 'Canary Wharf', 'walk_minutes' => 3]),
        room('me', ['location' => 'Mile End', 'latitude' => 51.5251, 'longitude' => -0.0332, 'nearest_station' => 'Mile End', 'walk_minutes' => 4]),
        // Says Canary Wharf in the title, is in Mile End: not "in Canary Wharf".
        room('title', ['title' => 'Double near Canary Wharf', 'location' => 'Mile End', 'latitude' => 51.5245, 'longitude' => -0.0330, 'nearest_station' => 'Mile End', 'walk_minutes' => 5]),
    ];
}

it('puts only rooms actually in the area in best matches, and nearby ones in other options', function () {
    fakeSigou(['location' => 'Canary Wharf', 'property_types' => []], canaryWharfFeed());
    $this->actingAs(User::factory()->create());

    $r = $this->postJson('/agent-search', ['q' => 'something in canary wharf'])->assertOk()->json();

    $best = collect($r['groups']['commission'])->concat($r['groups']['standard'])->pluck('id')->all();
    $other = collect($r['groups']['alternatives']);
    expect($best)->toBe(['cw']);
    expect($other->pluck('id')->sort()->values()->all())->toBe(['me', 'title']);
    expect($other->pluck('why')->implode(' '))->toContain('mi from Canary Wharf');
    expect($r['area'])->toBe('Canary Wharf');
    expect($r['widened'])->toBeFalse();
});

it('says plainly when nothing matches exactly, and still offers the closest', function () {
    fakeSigou(['location' => 'Canary Wharf', 'max_price' => 700, 'property_types' => []], canaryWharfFeed());
    $this->actingAs(User::factory()->create());

    $r = $this->postJson('/agent-search', ['q' => 'canary wharf under 700'])->assertOk()->json();

    expect($r['groups']['commission'])->toBe([]);
    expect($r['groups']['standard'])->toBe([]);
    expect($r['widened'])->toBeTrue();
    expect(collect($r['groups']['alternatives'])->pluck('why')->implode(' '))->toContain('over budget');
});

it('offers SpareRoom agent wildcards in their own band, opening on our page', function () {
    fakeSigou(['location' => 'Canary Wharf', 'property_types' => []], canaryWharfFeed());
    DB::table('market_listings')->insert([
        'spareroom_id' => '999', 'token' => MarketListingsService::tokenFor('999'), 'agency' => 'Dock Lettings', 'phone' => null,
        'data' => json_encode(room('x', ['location' => 'Canary Wharf', 'latitude' => 51.5049, 'longitude' => -0.0212, 'nearest_station' => 'Canary Wharf', 'walk_minutes' => 2, 'url' => 'https://www.spareroom.co.uk/999'])),
        'first_seen_at' => now(), 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->actingAs(User::factory()->create());

    $wild = $this->postJson('/agent-search', ['q' => 'canary wharf'])->assertOk()->json('groups.wildcards');

    expect($wild)->toHaveCount(1);
    expect($wild[0]['market'])->toBeTrue();
    expect($wild[0]['agent'])->toBe('Dock Lettings');
    expect($wild[0]['url'])->toContain('/market/')->not->toContain('spareroom');
});

it('records each question and which result was opened, for that agent only', function () {
    fakeSigou(['location' => 'Canary Wharf', 'property_types' => []], canaryWharfFeed());
    $agent = User::factory()->create();
    $this->actingAs($agent);

    $logId = $this->postJson('/agent-search', ['q' => 'something in canary wharf'])->assertOk()->json('log_id');
    $row = DB::table('assistant_interactions')->find($logId);
    expect($row->kind)->toBe('search');
    expect($row->query)->toBe('something in canary wharf');
    expect($row->best)->toBe(1);
    expect(json_decode($row->filters, true))->toMatchArray(['location' => 'Canary Wharf']);

    $this->post('/agent-search/click', ['log' => $logId, 'id' => 'cw', 'band' => 'best', 'pos' => 0])->assertNoContent();
    expect(json_decode(DB::table('assistant_interactions')->find($logId)->clicks, true)[0])->toMatchArray(['id' => 'cw', 'band' => 'best']);

    // Another agent cannot write into this log.
    $this->actingAs(User::factory()->create());
    $this->post('/agent-search/click', ['log' => $logId, 'id' => 'me', 'band' => 'other'])->assertNoContent();
    expect(json_decode(DB::table('assistant_interactions')->find($logId)->clicks, true))->toHaveCount(1);
});

it('hands over an agency\'s list and terms from the agencies sheet', function () {
    Cache::forever('agency_directory', [
        ['name' => 'Javier', 'link' => 'https://docs.google.com/spreadsheets/d/javier', 'max_age' => 37, 'commission' => '1 week', 'agent_share' => '60%', 'post_on_spareroom' => 'Yes', 'custom_sheet' => null],
    ]);
    (fn () => static::$memo = null)->bindTo(null, AgencyDirectory::class)();
    fakeSigou(['agency_request' => ['wanted' => true, 'name' => 'javier', 'want' => 'link']], [
        room('j1', ['agent_name' => 'Javier (JMS)']), room('j2', ['agent_name' => 'Javier (FENIX)']), room('o', ['agent_name' => 'Other']),
    ]);
    $this->actingAs(User::factory()->create());

    $this->postJson('/agent-search', ['q' => 'give me javier list'])->assertOk()
        ->assertJsonPath('agency.name', 'Javier')
        ->assertJsonPath('agency.link', 'https://docs.google.com/spreadsheets/d/javier')
        ->assertJsonPath('agency.max_age', 37)
        ->assertJsonPath('agency.rooms', 2);
});

it('shows an agency\'s rooms when asked what they have available', function () {
    Cache::forever('agency_directory', [['name' => 'Soreva', 'link' => null, 'max_age' => 38, 'commission' => '1 week', 'agent_share' => '60%', 'post_on_spareroom' => null, 'custom_sheet' => null]]);
    (fn () => static::$memo = null)->bindTo(null, AgencyDirectory::class)();
    fakeSigou(['agency_request' => ['wanted' => true, 'name' => 'soreva', 'want' => 'listings'], 'property_types' => []], [
        room('s1', ['agent_name' => 'Soreva Living']), room('o', ['agent_name' => 'Other Agency']),
    ]);
    $this->actingAs(User::factory()->create());

    $r = $this->postJson('/agent-search', ['q' => 'what does soreva have available'])->assertOk()->json();
    $ids = collect($r['groups']['commission'])->concat($r['groups']['standard'])->pluck('id')->all();

    expect($ids)->toBe(['s1']);
    expect($r['groups']['wildcards'])->toBe([]);
});

it('counts Javier as paying and Instabook as not, whatever the feed says', function () {
    $rates = app(CommissionRates::class);

    expect($rates->pays(['agent_name' => 'Javier (JMS)', 'paying' => '']))->toBeTrue();
    expect($rates->pays(['agent_name' => 'Javier (FENIX)']))->toBeTrue();
    expect($rates->pays(['agent_name' => 'Instabook Ltd', 'paying' => 'yes']))->toBeFalse();
    expect($rates->pays(['agent_name' => 'Gladstay Limited', 'paying' => 'no']))->toBeFalse();
});

it('says it cannot find a place rather than offering all of London', function () {
    fakeSigou(['location' => 'Atlantis', 'max_price' => 700, 'property_types' => []], canaryWharfFeed());
    $this->actingAs(User::factory()->create());

    $r = $this->postJson('/agent-search', ['q' => 'room in atlantis under 700'])->assertOk()->json();

    expect($r['unplaced'])->toBe('Atlantis');
    expect($r['groups']['standard'])->toBe([]);
    expect($r['groups']['alternatives'])->toBe([]);
});
