<?php

use App\Models\User;
use App\Services\WhatsAppPhotoStore;

beforeEach(function () {
    config(['suppliers.whatsapp.agencies.vic' => ['name' => 'Vic', 'group' => 'Vic-Truehold', 'deposit_weeks' => 1, 'fresh_days' => 14]]);
    exec('rm -rf ' . escapeshellarg(WhatsAppPhotoStore::root() . '/vic'));
});

function jpegData(int $seed = 1): string
{
    $img = imagecreatetruecolor(400, 300);
    imagefill($img, 0, 0, imagecolorallocate($img, $seed * 40 % 255, 120, 200));
    for ($i = 0; $i < 500; $i++) {
        imagesetpixel($img, random_int(0, 399), random_int(0, 299), imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
    }
    ob_start();
    imagejpeg($img, null, 90);

    return 'data:image/jpeg;base64,' . base64_encode(ob_get_clean());
}

it('saves a WhatsApp photo for an agent and serves it on the room', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson('/tools/whatsapp-photos', ['agency' => 'vic', 'postcode' => 'N9 8PE', 'street' => 'Tramway Avenue', 'room' => '', 'image' => jpegData()])
        ->assertOk()->assertJson(['saved' => true]);

    $urls = app(WhatsAppPhotoStore::class)->urlsFor('vic', 'N9 8PE', 'TRAMWAY AVENUE', '');
    expect($urls)->toHaveCount(1);
    $this->get($urls[0])->assertOk();

    // A room on the same street without its own photos borrows the street's.
    expect(app(WhatsAppPhotoStore::class)->urlsFor('vic', 'N98PE', 'Tramway Avenue', 'Double 2'))->toHaveCount(1);
});

it('refuses anything that is not a photo, an unknown agency, or a guest', function () {
    $this->postJson('/tools/whatsapp-photos', ['agency' => 'vic', 'postcode' => 'N9 8PE', 'image' => jpegData()])->assertUnauthorized();

    $this->actingAs(User::factory()->create());
    $this->postJson('/tools/whatsapp-photos', ['agency' => 'someone', 'postcode' => 'N9 8PE', 'image' => jpegData()])->assertStatus(422);
    $this->postJson('/tools/whatsapp-photos', ['agency' => 'vic', 'postcode' => 'N9 8PE', 'image' => 'data:image/jpeg;base64,' . base64_encode(str_repeat('x', 5000))])
        ->assertOk()->assertJson(['saved' => false]);
    $this->get('/wa-photo/../../.env')->assertNotFound();
});

afterAll(fn () => exec('rm -rf ' . escapeshellarg(storage_path('app/whatsapp-photos/vic'))));

it('never lends a street\'s photos to another street at the same postcode', function () {
    $this->actingAs(User::factory()->create());
    $this->postJson('/tools/whatsapp-photos', ['agency' => 'vic', 'postcode' => 'EN3 7AE', 'street' => 'SCOTLAND GREEN ROAD', 'image' => jpegData(2)])->assertJson(['saved' => true]);
    $this->postJson('/tools/whatsapp-photos', ['agency' => 'vic', 'postcode' => 'N16 5SH', 'street' => 'Listria Lodge', 'image' => jpegData(3)])->assertJson(['saved' => true]);
    $store = app(WhatsAppPhotoStore::class);

    expect($store->urlsFor('vic', 'EN3 7AE', 'Nags Head Road', ''))->toBe([]);
    expect($store->urlsFor('vic', 'EN3 7AE', 'Scotland Green Road', 'Double 1'))->toHaveCount(1);
    expect($store->urlsFor('vic', 'N16 5SH', 'Listria Lodge, Manor Road', ''))->toHaveCount(1);
});
