<?php

use App\Services\WhatsAppRoomsService;

/* Giaco's rules: Fausto no deposit, on the daily list; Vic 1 week, Fab 2
   weeks, gone after 10 days unseen; crossed out or taken = gone. */

function waRow(array $o = []): array
{
    // A Agency, B Status, C Area, D Street, E Postcode, F Room, G Price, H Per,
    // I Price for 2, J Room type, K Available from, L Posted, M Last seen, N Photos, O Baths, P Notes
    $row = ['Fausto', 'Available', 'TOTTENHAM HALE', 'Havelock Road', 'N17 9DR', 'B', '825', 'pcm', '', 'Double', 'Now',
        now()->format('d/m/Y'), now()->format('d/m/Y'), '', '', ''];
    foreach ($o as $i => $v) {
        $row[$i] = $v;
    }

    return $row;
}

it('reads a Fausto room: no deposit, defaults filled in, photos note for agents only', function () {
    $p = app(WhatsAppRoomsService::class)->mapRow(waRow());

    expect($p)->toMatchArray([
        'agent_name' => 'Fausto', 'title' => 'Havelock Road, Tottenham Hale — Room B', 'price' => 825.0,
        'deposit' => 0, 'total_rooms' => 4, 'bathrooms' => 1, 'room1_type' => 'double', 'postcode' => 'N17 9DR',
        'location' => 'Tottenham Hale', 'agent_note' => 'Photos in the Fausto-Truehold group WhatsApp group',
    ]);
    expect($p['description'])->not->toContain('Fausto')->not->toContain('WhatsApp');
});

it('turns a weekly Vic room into monthly, with a week of deposit and the couples price', function () {
    $p = app(WhatsAppRoomsService::class)->mapRow(waRow([0 => 'Vic', 2 => 'EDMONTON', 3 => 'TRAMWAY AVENUE', 4 => 'N9 8PE', 6 => '£170', 7 => 'pw', 8 => '£195']));

    expect($p['price'])->toBe(736.67);
    expect($p['couples_price'])->toBe(845.0);
    expect($p['couples_ok'])->toBe('Yes');
    expect($p['deposit'])->toBe(170.0);
});

it('gives Fab two weeks of deposit', function () {
    expect(app(WhatsAppRoomsService::class)->mapRow(waRow([0 => 'Fab', 6 => '800']))['deposit'])->toBe(round(800 * 12 / 52 * 2));
});

it('drops a room that is crossed out, taken, or not seen for too long', function () {
    $svc = app(WhatsAppRoomsService::class);

    expect($svc->mapRow(waRow([1 => 'Let'])))->toBeNull();
    // Fausto posts a list almost daily: five days off it and it is gone.
    expect($svc->mapRow(waRow([12 => now()->subDays(5)->format('d/m/Y')])))->toBeNull();
    // Vic and Fab: ten days.
    expect($svc->mapRow(waRow([0 => 'Vic', 11 => now()->subDays(9)->format('d/m/Y'), 12 => ''])))->not->toBeNull();
    expect($svc->mapRow(waRow([0 => 'Vic', 11 => now()->subDays(11)->format('d/m/Y'), 12 => ''])))->toBeNull();
    // An agency we do not know, or a row with no postcode.
    expect($svc->mapRow(waRow([0 => 'Someone'])))->toBeNull();
    expect($svc->mapRow(waRow([4 => ''])))->toBeNull();
});
