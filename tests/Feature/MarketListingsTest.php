<?php

use App\Models\User;
use App\Services\MarketListingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function wildcard(array $overrides = []): string
{
    $token = MarketListingsService::tokenFor('17868341');
    DB::table('market_listings')->insert([
        'spareroom_id' => '17868341',
        'token' => $token,
        'agency' => 'Marble Sales & Lettings',
        'phone' => null,
        'data' => json_encode($overrides + [
            'has_phone' => true,
            'price_checked' => 2,
            'title' => 'Double Room / No Deposit / All Bills Included',
            'price' => 850,
            'location' => 'Cricklewood',
            'postcode' => 'NW2',
            'latitude' => 51.5559, 'longitude' => -0.2139,
            'nearest_station' => 'Cricklewood', 'walk_minutes' => 5, 'zone' => 3,
            'description' => "Lovely room. Call Sam on 07700 900456 or sam@marble.test, www.marble.test",
            'photos' => ['https://photos2.spareroom.co.uk/images/flatshare/listings/large/1.jpg'],
            'url' => 'https://www.spareroom.co.uk/17868341',
        ]),
        'first_seen_at' => now(), 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $token;
}

it('shows an agent the wildcard on the normal Truehold page, with the agency and the original ad', function () {
    $token = wildcard();
    $this->actingAs(User::factory()->create());

    $this->get("/market/{$token}")->assertOk()
        ->assertSee('Double Room / No Deposit / All Bills Included')
        ->assertSee('Marble Sales &amp; Lettings', false)
        ->assertSee('View Original Listing')
        ->assertSee('Open in Google Maps')
        ->assertSee('spareroom.co.uk/17868341')
        ->assertDontSee('Wildcard')
        ->assertDontSee('not a partner');
});

it('shows a client the room but never who advertises it or how to reach them', function () {
    $token = wildcard();

    $html = $this->get("/market/{$token}")->assertOk()->getContent();

    expect($html)->toContain('Double Room / No Deposit / All Bills Included')
        ->not->toContain('Marble')
        ->not->toContain('07700')
        ->not->toContain('sam@marble.test')
        ->not->toContain('www.marble.test')
        ->not->toContain('17868341');
});

it('never puts the SpareRoom advert number in our URL', function () {
    $token = wildcard();

    expect($token)->not->toContain('17868341')->toMatch('/^[a-f0-9]{20}$/');
});

it('404s for anything that is not a live wildcard', function () {
    $this->get('/market/zzz')->assertNotFound();
    $this->get('/market/0123456789abcdef0123')->assertNotFound();
});

it('drops a wildcard not seen for four days from what Sigou offers', function () {
    wildcard();
    DB::table('market_listings')->update(['last_seen_at' => now()->subDays(5)]);

    expect(app(MarketListingsService::class)->all())->toHaveCount(0);
});

it('offers only wildcards whose advert has a number', function () {
    wildcard(['has_phone' => false]);

    expect(app(MarketListingsService::class)->all())->toHaveCount(0);
});

it('tells an advert that takes calls from one that does not, ignoring the support number', function () {
    $market = app(MarketListingsService::class);
    $footer = '<footer>Call us on 0161 768 1162</footer>';

    expect($market->hasPhone('<li class="contact_methods__li phoneadvertiser"><i class="far fa-phone"></i> Call</li>' . $footer))->toBeTrue();
    expect($market->hasPhone('<i class="far fa-phone page-tabs__icon"></i>' . $footer))->toBeTrue();
    expect($market->hasPhone('<li class="contact_methods__li emailadvertiser">Message</li>' . $footer))->toBeFalse();
});

it('offers only zones 1 to 3', function () {
    wildcard(['zone' => 4]);

    expect(app(MarketListingsService::class)->all())->toHaveCount(0);
});

it('knows when a search holds more than SpareRoom will list', function () {
    expect(MarketListingsService::isFull('<p>Showing 1-10 of <strong>1000+</strong> results</p>'))->toBeTrue();
    expect(MarketListingsService::isFull('<p>Showing 1-10 of <strong>899</strong> results</p>'))->toBeFalse();
});

it('reads a SpareRoom results page: agents that are free to contact', function () {
    $html = '<article data-listing-id="1" data-listing-advertiser-role="agent" data-listing-early-bird="" data-listing-neighbourhood="Bow" data-listing-postcode="E3">'
        . '<article data-listing-id="2" data-listing-advertiser-role="live out landlord" data-listing-early-bird="">'
        . '<article data-listing-id="3" data-listing-advertiser-role="agent" data-listing-early-bird="Y">';

    $cards = app(MarketListingsService::class)->cards($html);

    expect($cards['1'])->toMatchArray(['role' => 'agent', 'early_bird' => '', 'neighbourhood' => 'Bow', 'postcode' => 'E3']);
    expect($cards['3']['early_bird'])->toBe('Y');
});

it('finds the agency name even behind SpareRoom\'s inline scripts', function () {
    $html = '<script>if(a<b){x()}</script><a href="#"> Show interest  </a>and we will send Marble Sales &amp; Lettings your profile';

    expect(app(MarketListingsService::class)->agency($html))->toBe('Marble Sales & Lettings');
});

it('leaves out an agency that is switched off, however its name is written', function () {
    config(['suppliers.feed_agencies' => [['name' => 'Cloudrooms', 'user_id' => '1', 'seeds' => [], 'active' => false]]]);

    expect(MarketListingsService::paused('Cloud Rooms'))->toBeTrue();
    expect(MarketListingsService::paused('Cloudrooms Ltd'))->toBeTrue();
    expect(MarketListingsService::paused('Marble Lettings'))->toBeFalse();
});

it('turns a weekly rent into monthly, and quotes the lower end of a range', function () {
    $page = fn (string $summary) => '<meta property="og:description" content="All Saints : ' . $summary . '. Lovely flat">'
        . '<h1>Lovely Flat</h1><p>from &pound;170</p><p>pw</p>';
    $advert = app(\App\Services\SpareRoomAdvertService::class);
    $price = new ReflectionMethod($advert, 'price');

    expect($price->invoke($advert, [], $page('&pound;170 pw (inc bills)')))->toBe(736.67);
    expect($price->invoke($advert, [], $page('&pound;982-&pound;1,046 pcm (inc bills)')))->toBe(982.0);
    expect($price->invoke($advert, ['£839 pcm'], ''))->toBe(839.0);
    // A bare "from £170" of another advert is not this one's price.
    expect($price->invoke($advert, ['from £170', 'pw'], ''))->toBeNull();
});

it('reads the area, district and station from the advert\'s own key features', function () {
    $advert = app(\App\Services\SpareRoomAdvertService::class);

    expect($advert->keyFeatures(['Lovely flat', 'Flat share', 'All Saints', 'E14 Area info', 'Langdon Park Station', 'Tube map']))
        ->toBe(['area' => 'All Saints', 'outcode' => 'E14', 'station' => 'Langdon Park']);
    expect($advert->keyFeatures(['Flat share', 'Whitechapel', 'E1', 'Area info', 'Whitechapel Station']))
        ->toBe(['area' => 'Whitechapel', 'outcode' => 'E1', 'station' => 'Whitechapel']);
});

it('holds back a cheap wildcard read before weekly rents were converted', function () {
    wildcard(['price' => 170, 'price_checked' => null]);

    expect(app(MarketListingsService::class)->all())->toHaveCount(0);
});
