<?php

use App\Models\User;
use App\Services\AgentSearchAssistant;
use App\Services\ScrapedListingsApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $fake = Mockery::mock(AgentSearchAssistant::class)->makePartial();
    $fake->shouldReceive('isConfigured')->andReturn(true);
    $fake->shouldReceive('parse')->andReturn(['wifi' => true, 'sigou' => 'Here bro']);
    app()->instance(AgentSearchAssistant::class, $fake);

    $feed = Mockery::mock(ScrapedListingsApiService::class);
    $feed->shouldReceive('getAllProperties')->andReturn(collect());
    app()->instance(ScrapedListingsApiService::class, $feed);
});

it('hands an agent the office wifi from config', function () {
    config(['services.office_wifi' => ['ssid' => 'Office', 'password' => 'secret-pass', 'qr_url' => 'https://example.test/qr']]);
    $this->actingAs(User::factory()->create());

    $this->postJson('/agent-search', ['q' => 'wifi pls'])
        ->assertOk()
        ->assertJsonPath('wifi.ssid', 'Office')
        ->assertJsonPath('wifi.password', 'secret-pass')
        ->assertJsonPath('wifi.qr_url', 'https://example.test/qr');
});

it('gives a QR code to scan, not just a password to type', function () {
    config(['services.office_wifi' => ['ssid' => 'Office', 'password' => 'secret-pass', 'qr_url' => null]]);
    $this->actingAs(User::factory()->create());

    $qr = $this->postJson('/agent-search', ['q' => 'wifi pls'])->assertOk()->json('wifi.qr');

    expect($qr)->toContain('<svg');
});

it('says so when nobody has set the wifi', function () {
    config(['services.office_wifi' => ['ssid' => null, 'password' => null, 'qr_url' => null]]);
    $this->actingAs(User::factory()->create());

    $this->postJson('/agent-search', ['q' => 'wifi pls'])
        ->assertOk()
        ->assertJsonPath('wifi', null)
        ->assertJsonPath('sigou', 'Nobody told me the WiFi yet, ask Giaco');
});

it('never gives the wifi to someone who is not signed in', function () {
    config(['services.office_wifi' => ['ssid' => 'Office', 'password' => 'secret-pass', 'qr_url' => null]]);

    $this->postJson('/agent-search', ['q' => 'wifi pls'])->assertUnauthorized();
});
