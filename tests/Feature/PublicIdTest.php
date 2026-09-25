<?php

use App\Services\ScrapedListingsApiService;
use App\Support\PropertyPayload;
use App\Support\PublicId;

beforeEach(function () {
    config(['services.harborops.base_url' => 'https://example.test', 'services.harborops.api_key' => 'k']);
    $feed = Mockery::mock(ScrapedListingsApiService::class)->makePartial();
    $feed->shouldReceive('getAllProperties')->andReturn(collect([
        ['id' => 'spareroom-18422285', 'title' => 'Double room', 'price' => 900, 'photos' => []],
    ]));
    app()->instance(ScrapedListingsApiService::class, $feed);
});

it('never names the source in a property address', function () {
    expect(PublicId::for('spareroom-18422285'))->toMatch('/^[a-f0-9]{12}$/')
        ->not->toContain('spareroom');
});

it('sends an old spareroom link to the neutral address', function () {
    $this->get('/properties/spareroom-18422285?x=1')
        ->assertStatus(301)
        ->assertRedirect(route('properties.show', PublicId::for('spareroom-18422285')) . '?x=1');
});

it('finds the property by its neutral address', function () {
    expect(app(ScrapedListingsApiService::class)->getPropertyById(PublicId::for('spareroom-18422285')))
        ->toMatchArray(['id' => 'spareroom-18422285']);
});

it('gives a client only the neutral id', function () {
    $guest = PropertyPayload::one(['id' => 'spareroom-18422285', 'spareroom_id' => '18422285'], false);
    $agent = PropertyPayload::one(['id' => 'spareroom-18422285'], true);

    expect(json_encode($guest))->not->toContain('18422285')
        ->and($guest['id'])->toBe(PublicId::for('spareroom-18422285'))
        ->and($agent['id'])->toBe('spareroom-18422285')
        ->and($agent['public_id'])->toBe(PublicId::for('spareroom-18422285'));
});
