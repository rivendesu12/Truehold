<?php

/**
 * Suppliers we source directly, rather than through the Harbor Ops feed.
 *
 * Two kinds:
 *  - `spareroom`: landlords and agencies who advertise on SpareRoom. We hold a
 *    seed advert per advertiser and crawl "more from the same advertiser" to
 *    find the rest, so their new rooms appear without anyone adding a link.
 *  - `sheets`: agencies who keep their stock in a Google Sheet.
 */
return [

    'spareroom' => [
        // Politeness: these are someone else's pages. One request at a time,
        // with a pause, and a cap on how far a crawl can wander.
        'delay_ms' => 700,
        'max_adverts_per_advertiser' => 40,
        'cache_timeout' => env('SUPPLIER_SPAREROOM_CACHE', 1800),

        // `user_id` is the advertiser's own SpareRoom id, read off the report
        // link on any of their adverts. Every crawled advert is checked
        // against it, so a crawl cannot drift onto someone else's stock.
        'advertisers' => [
            [
                'name' => 'DC Lettings',
                'user_id' => '7105165',
                'seeds' => ['18335108'],
                'pays_commission' => true,
            ],
            [
                'name' => 'Antonio',
                'user_id' => '5072610',
                'seeds' => ['18321384'],
                'pays_commission' => true,
            ],
            [
                // Two user ids: the two adverts given for Life Stay belong to
                // different SpareRoom accounts. Both are treated as Life Stay.
                'name' => 'Life Stay',
                'user_id' => '24446303',
                'seeds' => ['18362131'],
                'pays_commission' => true,
            ],
            [
                'name' => 'Life Stay',
                'user_id' => '25214999',
                'seeds' => ['18365277'],
                'pays_commission' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Company codes in the scraped spreadsheets
    |--------------------------------------------------------------------------
    |
    | The feed's spreadsheet rows carry the operating company in their raw
    | data but no agency name, so 82 listings were anonymous: they could not
    | be filtered by agency, and a commission search could never reach them.
    | Javier trades as both JMS and FENIX.
    |
    */
    'sheet_companies' => [
        'jms' => 'Javier (JMS)',
        'fenix' => 'Javier (FENIX)',
        'smart share' => 'Smart Share',
        'smartshare' => 'Smart Share',
    ],

    'soreva' => [
        'spreadsheet_id' => env('SOREVA_SHEET_ID', '1klNq5RWcbMYkW1dbUz0oYTE2ss8CB5zgX-PKsoOhCkE'),
        // The tab written for letting agents. The other tabs are internal
        // status tracking, which we never read; and this tab itself carries
        // tenant name, phone, date of birth and email in columns L-P, which
        // is why the reader stops at K (SorevaSheetService::RANGE).
        'tab' => env('SOREVA_SHEET_TAB', 'Letting agent List'),
        'cache_timeout' => env('SOREVA_CACHE_TIMEOUT', 900),
        'pays_commission' => true,
        // A row counts as available when its status begins with AVAILABLE.
        // BOOKED, ON HOLD and LEASE COMFIRMED are not.
        'available_prefix' => 'available',
    ],

];
