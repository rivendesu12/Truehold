<?php

use App\Services\ZooplaLeadParser;

/*
 * Shaped like a real Zoopla enquiry: multipart/alternative, a plain part
 * labelled 7bit but written quoted-printable, the price only in the HTML.
 * The applicant is made up.
 */
function zooplaEnquiry(string $ref = ''): string
{
    $plain = "Dear team at Joy Homes London\r\nYou have a new=20\r\n    Tenant enquiry.\r\n\r\n"
        . "TENANT ENQUIRY FROM JANE EXAMPLE VIA ZOOPLA\r\n\r\nPlease respond to this enquiry promptly to maximise your chances of convert=\r\ning this lead.\r\n\r\n---\r\n\r\n"
        . "APPLICANT'S DETAILS\r\n\r\n\r\nName: Jane Example\r\nEmail address: Jane.Example@example.com\r\n"
        . "Telephone number: 07700 900123\r\nType of enquiry: Looking to rent\r\nAddress: Smart Street, Bethnal Green E2\r\n\r\n\r\n---\r\n\r\n"
        . "Full address: Smart Street, Bethnal Green E2\r\n\r\nYour property ref:" . ($ref === '' ? '=20' : " {$ref}") . "\r\n\r\n"
        . "http://www.zoopla.co.uk/to-rent/details/74316374/?utm_source=3Dlead\r\n\r\nWe hope that this lead proves useful to you.\r\n";

    $html = "<html><body><table><tr><td>Name:</td><td class=3D\"v\">Jane Example</td></tr>\r\n"
        . "<tr><td>Telephone:</td><td>07700 900123</td></tr>\r\n"
        . "<tr><td>Unique Reference:</td><td>listing_184159065_170196</td></tr></table>\r\n"
        . "<p><b>=C2=A3820 pcm</b> - 5 bed flat to rent</p></body></html>\r\n";

    $b = '_----------=_17901711991554400327';

    return "Message-Id: <202609231346.68NDNto7050057@mail.authsmtp.com>\r\n"
        . "Content-Type: multipart/alternative; boundary=\"{$b}\"\r\nMIME-Version: 1.0\r\n"
        . "Date: Wed, 23 Sep 2026 14:46:39 +0100\r\n"
        . 'Subject: =?UTF-8?B?' . base64_encode('Tenant enquiry from Jane Example via Zoopla') . "?=\r\n"
        . 'Reply-To: =?UTF-8?B?' . base64_encode('Jane Example') . "?= <jane.example@example.com>\r\n"
        . "From: members@zoopla.co.uk\r\n\r\nThis is a multi-part message in MIME format.\r\n\r\n"
        . "--{$b}\r\nContent-Disposition: inline\r\nContent-Transfer-Encoding: 7bit\r\nContent-Type: text/plain;charset=utf-8\r\n\r\n{$plain}\r\n"
        . "--{$b}\r\nContent-Disposition: inline\r\nContent-Transfer-Encoding: quoted-printable\r\nContent-Type: text/html;charset=utf-8\r\n\r\n{$html}\r\n"
        . "--{$b}--\r\n";
}

it('reads a Zoopla tenant enquiry', function () {
    $lead = ZooplaLeadParser::parse(zooplaEnquiry());

    expect($lead)->toMatchArray([
        'name' => 'Jane Example',
        'first_name' => 'Jane',
        'surname' => 'Example',
        'phone' => '07700 900123',
        'phone_intl' => '447700900123',
        'email' => 'jane.example@example.com',
        'enquiry_type' => 'Looking to rent',
        'address' => 'Smart Street, Bethnal Green E2',
        'area' => 'Bethnal Green',
        'price' => 820,
        'property_link' => 'http://www.zoopla.co.uk/to-rent/details/74316374/',
        'listing_ref' => 'listing_184159065_170196',
        'message_id' => '202609231346.68NDNto7050057@mail.authsmtp.com',
    ]);
    // A blank property ref must not pick up the advert link on the next line.
    expect($lead['property_ref'])->toBeNull();
    expect($lead['received_at']->format('Y-m-d H:i'))->toBe('2026-09-23 14:46');
});

it('keeps our own property ref when the advert has one', function () {
    expect(ZooplaLeadParser::parse(zooplaEnquiry('Jessica'))['property_ref'])->toBe('Jessica');
});

it('ignores Zoopla emails that are not enquiries', function () {
    $raw = str_replace(
        base64_encode('Tenant enquiry from Jane Example via Zoopla'),
        base64_encode('Your Zoopla one-time verification code is inside.'),
        zooplaEnquiry()
    );

    expect(ZooplaLeadParser::parse($raw))->toBeNull();
});

it('puts phone numbers in the CRM form', function () {
    expect(ZooplaLeadParser::internationalPhone('07340 085125'))->toBe('447340085125');
    expect(ZooplaLeadParser::internationalPhone('+44 7340 085125'))->toBe('447340085125');
    expect(ZooplaLeadParser::internationalPhone('0048 662 678 832'))->toBe('48662678832');
    expect(ZooplaLeadParser::internationalPhone(''))->toBeNull();
});

it('takes the area from the address', function () {
    expect(ZooplaLeadParser::area('Smart Street, Bethnal Green E2'))->toBe('Bethnal Green');
    expect(ZooplaLeadParser::area('Limehouse E14'))->toBe('Limehouse');
    expect(ZooplaLeadParser::area('Mayfield Road, Walthamstow E17 4AB'))->toBe('Walthamstow');
    expect(ZooplaLeadParser::area('Canary Wharf, E14'))->toBe('Canary Wharf');
});

describe('the password page', function () {
    beforeEach(function () {
        $this->app->useStoragePath(sys_get_temp_dir() . '/zoopla-leads-test-' . uniqid());
        config(['services.zoopla_leads.password' => null]);
    });

    it('is for admins only', function () {
        $this->actingAs(\App\Models\User::factory()->create(['role' => 'agent']))
            ->get('/admin/zoopla-leads')->assertForbidden();
        $this->post('/admin/zoopla-leads', ['password' => 'x'])->assertForbidden();
        expect(\App\Support\ZooplaLeadsSettings::password())->toBeNull();
    });

    it('stores the password encrypted and never shows it back', function () {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/admin/zoopla-leads', ['password' => 'correct horse 42'])
            ->assertRedirect('/admin/zoopla-leads');

        expect(\App\Support\ZooplaLeadsSettings::password())->toBe('correct horse 42');
        expect(file_get_contents(\App\Support\ZooplaLeadsSettings::path()))->not->toContain('correct horse');

        $this->actingAs($admin)->get('/admin/zoopla-leads')
            ->assertOk()
            ->assertSee('A password is saved')
            ->assertDontSee('correct horse 42');
    });
});

it('saves nothing when no password is typed', function () {
    $this->app->useStoragePath(sys_get_temp_dir() . '/zoopla-leads-test-' . uniqid());
    config(['services.zoopla_leads.password' => null]);

    $this->artisan('zoopla:password')
        ->expectsQuestion('Zoho password for hello@joyhomeslondon.co.uk (hidden as you type)', '')
        ->assertFailed();

    expect(\App\Support\ZooplaLeadsSettings::password())->toBeNull();
});
