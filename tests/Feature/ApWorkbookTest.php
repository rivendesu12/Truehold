<?php

use App\Services\ApPortfolioPriceService as Ap;

/* Giaco's rule: "Available" and not booked. ROLLING means someone lives there. */

it('offers only rows that say available and are not booked', function () {
    expect(Ap::isAvailable('30 Sep 2026(Available)'))->toBeTrue();
    expect(Ap::isAvailable('30 October ( Available)'))->toBeTrue();
    expect(Ap::isAvailable('ROLLING'))->toBeFalse();
    expect(Ap::isAvailable('NOW-BOOKED'))->toBeFalse();
    expect(Ap::isAvailable('1 Nov 2026 ( Available)-Booked'))->toBeFalse();
    expect(Ap::isAvailable('30 October 2026 (TBC)'))->toBeFalse();
    expect(Ap::isAvailable('30 Sep 2026-BOOKED'))->toBeFalse();
});

it('reads the date out of the status, and a missing year as the next one', function () {
    $this->travelTo('2026-09-23');
    expect(Ap::availableFrom('20 Oct 2026(Available )')->toDateString())->toBe('2026-10-20');
    expect(Ap::availableFrom('30 October ( Available)')->toDateString())->toBe('2026-10-30');
    expect(Ap::availableFrom('12 Jan 2027- (Available)')->toDateString())->toBe('2027-01-12');
    expect(Ap::availableFrom('01 Sep 2026 (Available)')->toDateString())->toBe('2026-09-23'); // past = now
});

it('works out ages from both date-of-birth formats in the workbook', function () {
    $this->travelTo('2026-09-23');
    expect(Ap::age('04/03/2003'))->toBe(23);
    expect(Ap::age(37684))->toBe(23); // Excel serial for 03/03/2003
    expect(Ap::age('Kathryn'))->toBeNull();
});
