<?php

use App\Services\JavierSheetService;

/* Giaco's rule: MOVE OUT or APT BREAK only; on hold or blank is not a vacancy. */

it('offers only MOVE OUT and APT BREAK rooms', function () {
    expect(JavierSheetService::isAvailable('MOVE OUT'))->toBeTrue();
    expect(JavierSheetService::isAvailable('APT BREAK'))->toBeTrue();
    expect(JavierSheetService::isAvailable('MOVE OUT/HOLD'))->toBeFalse();
    expect(JavierSheetService::isAvailable('ON HOLD'))->toBeFalse();
    expect(JavierSheetService::isAvailable(''))->toBeFalse();
});

it('reads a room row and never takes the tenant columns', function () {
    $in = now()->addDays(10);
    // B..N as the API returns them for the B1:N range.
    $row = ['JMS', 'MOVE OUT', $in->format('D d/m/y'), 'NOT CONFIRMED', '1', 'Oval / Kennington', '56 Tyneham Close', 'SW11 5XW', 'Double', '2', '866', '1250', 'All included'];
    $p = app(JavierSheetService::class)->mapRow($row, ['56 tyneham close' => 'https://drive.google.com/drive/folders/abc']);

    expect($p)->toMatchArray([
        'agent_name' => 'Javier (JMS)', 'title' => '56 Tyneham Close — Room 2', 'price' => 866.0,
        'room1_type' => 'double', 'couples_ok' => 'Yes', 'couples_price' => 1250.0, 'bills_included' => 'Yes',
        'available_date' => $in->toDateString(), 'availability_note' => 'Move-out not confirmed yet', 'location' => 'Oval',
    ]);

    $held = $row; $held[1] = 'MOVE OUT/HOLD';
    expect(app(JavierSheetService::class)->mapRow($held))->toBeNull();

    $heading = ['6 ROOM(S) OCTOBER', '', '', '', '', '', '', 'OCTOBER 2026', '6 ROOM(S)'];
    expect(app(JavierSheetService::class)->mapRow($heading))->toBeNull();
});
