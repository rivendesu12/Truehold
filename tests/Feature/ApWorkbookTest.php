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

it('reads cell links whatever order the attributes come in', function () {
    $f = storage_path('framework/testing/links.xlsx');
    @mkdir(dirname($f), 0777, true);
    @unlink($f);
    $z = new ZipArchive();
    $z->open($f, ZipArchive::CREATE);
    $z->addFromString('xl/workbook.xml', '<workbook xmlns:r="r"><sheets><sheet name="S" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $z->addFromString('xl/_rels/workbook.xml.rels', '<Relationships><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>');
    $z->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns:r="r"><sheetData><row r="3"><c r="C3" t="inlineStr"><is><t>Ramsey</t></is></c></row></sheetData>'
        . '<hyperlinks><hyperlink r:id="rId7" ref="C3"/><hyperlink ref="D3" r:id="rId8"/></hyperlinks></worksheet>');
    $z->addFromString('xl/worksheets/_rels/sheet1.xml.rels', '<Relationships><Relationship Id="rId7" Target="https://drive.google.com/a?x=1&amp;y=2"/><Relationship Id="rId8" Target="https://drive.google.com/b"/></Relationships>');
    $z->close();

    expect(\App\Support\XlsxReader::hyperlinks($f, 'S'))->toBe(['3:3' => 'https://drive.google.com/a?x=1&y=2', '3:4' => 'https://drive.google.com/b']);
    expect(\App\Support\XlsxReader::rows($f, 'S')[0])->toBe([3 => 'Ramsey', '_row' => 3]);
    unlink($f);
});
