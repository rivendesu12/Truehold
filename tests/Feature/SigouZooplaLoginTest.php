<?php

use App\Models\User;
use App\Services\AgentSearchAssistant;
use App\Services\ScrapedListingsApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $fake = Mockery::mock(AgentSearchAssistant::class)->makePartial();
    $fake->shouldReceive('isConfigured')->andReturn(true);
    $fake->shouldReceive('parse')->andReturn(['zoopla_login' => true, 'sigou' => 'Here bro']);
    app()->instance(AgentSearchAssistant::class, $fake);

    $feed = Mockery::mock(ScrapedListingsApiService::class);
    $feed->shouldReceive('getAllProperties')->andReturn(collect());
    app()->instance(ScrapedListingsApiService::class, $feed);
});

it('hands an agent the zoopla login from config', function () {
    config(['services.zoopla_login' => ['email' => 'hello@example.test', 'password' => 'secret-pass', 'url' => 'https://pro.zoopla.co.uk']]);
    $this->actingAs(User::factory()->create());

    $this->postJson('/agent-search', ['q' => 'zoopla login pls'])
        ->assertOk()
        ->assertJsonPath('zoopla_login.email', 'hello@example.test')
        ->assertJsonPath('zoopla_login.password', 'secret-pass')
        ->assertJsonPath('zoopla_login.url', 'https://pro.zoopla.co.uk');
});

it('says so when nobody has set the zoopla login', function () {
    config(['services.zoopla_login' => ['email' => null, 'password' => null, 'url' => 'https://pro.zoopla.co.uk']]);
    $this->actingAs(User::factory()->create());

    $this->postJson('/agent-search', ['q' => 'zoopla login pls'])
        ->assertOk()
        ->assertJsonPath('zoopla_login', null)
        ->assertJsonPath('sigou', 'Nobody gave me the Zoopla login yet, ask Giacomo or Pasquale');
});

it('never gives the zoopla login to someone who is not signed in', function () {
    config(['services.zoopla_login' => ['email' => 'hello@example.test', 'password' => 'secret-pass', 'url' => null]]);

    $this->postJson('/agent-search', ['q' => 'zoopla login pls'])->assertUnauthorized();
});
