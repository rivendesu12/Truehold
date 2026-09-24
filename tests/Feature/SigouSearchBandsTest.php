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
        'data' => json_encode(room('x', ['location' => 'Canary Wharf', 'latitude' => 51.5049, 'longitude' => -0.0212, 'nearest_station' => 'Canary Wharf', 'walk_minutes' => 2, 'zone' => 2, 'has_phone' => true, 'url' => 'https://www.spareroom.co.uk/999'])),
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

it('hands over an agency\'s bank details from the private file, by any of its names', function () {
    $path = storage_path('framework/testing/agency-bank-details.json');
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, json_encode(['agencies' => [
        ['name' => 'AP Real Estate', 'group' => 'AP Horizon', 'aliases' => ['ap'],
            'accounts' => [['bank' => 'Test Bank', 'account_name' => 'AP Test', 'sort_code' => '00-00-00', 'account_number' => '11111111']],
            'forms' => [['label' => 'AP holding deposit form', 'url' => 'https://forms.example/ap']]],
        ['name' => 'Horizon Dreams', 'group' => 'AP Horizon', 'aliases' => ['horizon'],
            'accounts' => [['bank' => 'Test Bank', 'account_name' => 'Horizon Test', 'sort_code' => '00-00-01', 'account_number' => '12345678']]],
    ]]));
    config(['truehold.agency_bank_details' => $path]);
    fakeSigou(['agency_request' => ['wanted' => true, 'name' => 'horizon', 'want' => 'bank']], []);
    $this->actingAs(User::factory()->create());

    $this->postJson('/agent-search', ['q' => 'horizon bank details'])->assertOk()
        ->assertJsonPath('agency_bank.name', 'Horizon Dreams')
        ->assertJsonCount(1, 'agency_bank.accounts')
        ->assertJsonPath('agency_bank.accounts.0.account_number', '12345678');

    // AP and Horizon bank apart; asking for both gives both, labelled.
    fakeSigou(['agency_request' => ['wanted' => true, 'name' => 'ap horizon', 'want' => 'bank']], []);
    $this->postJson('/agent-search', ['q' => 'ap horizon bank details'])->assertOk()
        ->assertJsonPath('agency_bank.name', 'AP Horizon')
        ->assertJsonPath('agency_bank.accounts.0.company', 'AP Real Estate')
        ->assertJsonPath('agency_bank.accounts.1.company', 'Horizon Dreams');

    // An agency with none on file gets told so, not someone else's account.
    fakeSigou(['agency_request' => ['wanted' => true, 'name' => 'banksia', 'want' => 'bank']], []);
    $this->postJson('/agent-search', ['q' => 'banksia bank details'])->assertOk()
        ->assertJsonPath('agency_bank', null);

    // Never for someone logged out.
    auth()->logout();
    $this->postJson('/agent-search', ['q' => 'horizon bank details'])->assertUnauthorized();

    @unlink($path);
});

it('answers who lives at a property with that property\'s rooms and their flatmates', function () {
    fakeSigou(['property_lookup' => 'netherby house', 'property_types' => []], [
        room('n1', ['title' => 'Netherby House — Room C', 'source_property' => 'Netherby House', 'household' => ['count' => 4, 'ages' => '22 to 27', 'source' => 'agency sheet']]),
        room('s1', ['title' => 'Double near Stratford', 'household' => ['count' => 3, 'gender' => '2 Females, 1 Male', 'ages' => '24 to 30', 'occupation' => 'Professionals']]),
    ]);
    $this->actingAs(User::factory()->create());

    $r = $this->postJson('/agent-search', ['q' => 'who lives in netherby house'])->assertOk()->json();
    $best = collect($r['groups']['commission'])->concat($r['groups']['standard']);

    expect($r['lookup'])->toBe('netherby house');
    expect($best->pluck('id')->all())->toBe(['n1']);
    expect($best[0]['household'])->toBe('4 flatmates · aged 22 to 27');
    expect(json_encode($r))->not->toContain('raw_row');
});

it('writes a household the way an agent reads it', function () {
    expect(\App\Support\Household::summary(['household' => ['count' => 3, 'gender' => '2 Females, 1 Male', 'ages' => '24 to 30', 'occupation' => 'Professionals']]))
        ->toBe('3 flatmates · 2 females, 1 male · aged 24 to 30 · professionals');
    expect(\App\Support\Household::summary(['household' => ['count' => 3, 'gender' => '3 Males', 'occupation' => 'Other']]))
        ->toBe('3 flatmates · 3 males');
    expect(\App\Support\Household::summary(['housemates' => 1]))->toBe('1 flatmate');
    expect(\App\Support\Household::summary([]))->toBeNull();
    expect(\App\Support\Household::isProperty(['title' => '53 Fursecroft — D5'], '53 fursecroft'))->toBeTrue();
    expect(\App\Support\Household::isProperty(['title' => '5 Fursecroft Road'], '53 fursecroft'))->toBeFalse();
});

it('leaves an agency out when told "no X", wherever the model put it', function () {
    $feed = [
        room('c1', ['location' => 'Stratford', 'agent_name' => 'Cloudrooms Ltd']),
        room('o1', ['location' => 'Stratford', 'agent_name' => 'Capital Living']),
    ];
    $this->actingAs(User::factory()->create());

    foreach ([['agencies' => ['no cloudrooms']], ['exclude_agencies' => ['Cloud Rooms']]] as $spec) {
        fakeSigou($spec + ['property_types' => []], $feed);
        $r = $this->postJson('/agent-search', ['q' => 'no cloudrooms please'])->assertOk()->json();
        expect(collect($r['groups']['commission'])->concat($r['groups']['standard'])->pluck('id')->all())->toBe(['o1']);
    }
});

it('hands over our own Room targets sheet', function () {
    config(['truehold.room_targets_url' => 'https://docs.google.com/spreadsheets/d/targets/edit']);
    fakeSigou(['agency_request' => ['wanted' => true, 'name' => 'room targets', 'want' => 'link']], []);
    $this->actingAs(User::factory()->create());

    $this->postJson('/agent-search', ['q' => 'room targets link'])->assertOk()
        ->assertJsonPath('agency.name', 'Room targets')
        ->assertJsonPath('agency.link', 'https://docs.google.com/spreadsheets/d/targets/edit');
});

it('offers a room that is nearby and a little over budget when little else fits', function () {
    // Stratford station 51.5416, -0.0033; Manor Park 51.5524, 0.0463 (~2.2 mi).
    fakeSigou(['location' => 'Stratford', 'max_price' => 700, 'property_types' => []], [
        room('s1', ['location' => 'Stratford', 'price' => 700, 'latitude' => 51.5420, 'longitude' => -0.0030, 'nearest_station' => 'Stratford', 'walk_minutes' => 2]),
        room('mp', ['location' => 'Manor Park', 'price' => 800, 'latitude' => 51.5524, 'longitude' => 0.0463, 'nearest_station' => 'Manor Park', 'walk_minutes' => 3]),
        room('far', ['location' => 'Romford', 'price' => 800, 'latitude' => 51.5750, 'longitude' => 0.1830, 'nearest_station' => 'Romford', 'walk_minutes' => 3]),
        room('dear', ['location' => 'Manor Park', 'price' => 1000, 'latitude' => 51.5524, 'longitude' => 0.0463, 'nearest_station' => 'Manor Park', 'walk_minutes' => 3]),
    ]);
    $this->actingAs(User::factory()->create());

    $other = collect($this->postJson('/agent-search', ['q' => 'stratford 700'])->assertOk()->json('groups.alternatives'));

    expect($other->pluck('id')->all())->toBe(['mp']);
    expect($other[0]['why'])->toContain('mi from Stratford')->toContain('100 over budget');
});
