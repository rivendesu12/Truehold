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
        // Six hours: some thirty advertisers, a few hundred adverts a crawl.
        'cache_timeout' => env('SUPPLIER_SPAREROOM_CACHE', 21600),

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
    | The agencies Ali's HarborOps feed used to bring in, crawled by us instead
    | (23 Sep 2026). Seeds and account ids were read off the adverts his feed
    | held; the commission flag is what his feed said, and config/commission.php
    | still overrides it (Instabook never pays). "Gigi" was Life Stay's own
    | account and "Cloud Rooms" the same account as Cloudrooms.
    */
    'feed_agencies' => [
            ['name' => 'Alchemy Accommodation', 'user_id' => '23190366', 'seeds' => ['17757673', '18330619', '18271886'], 'pays_commission' => true],
            ['name' => 'Banksia Rooms', 'user_id' => '9042565', 'seeds' => ['18426202', '18426096', '18426171'], 'pays_commission' => true],
            ['name' => 'BOND & MAIN GROUP LTD', 'user_id' => '24055614', 'seeds' => ['18418088', '18425508'], 'pays_commission' => false],
            ['name' => 'Built Asset Managment', 'user_id' => '19457130', 'seeds' => ['18419479', '18427414', '18420785'], 'pays_commission' => true],
            ['name' => 'Capital Living', 'user_id' => '1001446', 'seeds' => ['18408567', '18421359', '18427461'], 'pays_commission' => false],
            ['name' => 'Choices', 'user_id' => '675357', 'seeds' => ['18410154', '18381906', '18290550'], 'pays_commission' => false],
            ['name' => 'City Spare Lodge', 'user_id' => '3630781', 'seeds' => ['18425986', '18416733', '18271514'], 'pays_commission' => false],
            ['name' => 'Cloudrooms', 'user_id' => '18517699', 'seeds' => ['18420740', '18420997', '18420957', '18427386', '18420784', '18369974'], 'pays_commission' => false],
            ['name' => 'Come To London Limited', 'user_id' => '19672223', 'seeds' => ['18392751', '17810660', '16754935'], 'pays_commission' => false],
            ['name' => 'Depa Properties LTD', 'user_id' => '23039761', 'seeds' => ['18412855', '18401776', '18374572'], 'pays_commission' => false],
            ['name' => 'EASTERN HOMES PROPERTIES LTD', 'user_id' => '23328512', 'seeds' => ['17273262', '18223329', '18384474'], 'pays_commission' => false],
            ['name' => 'Gladstay Limited', 'user_id' => '1999675', 'seeds' => ['18422226'], 'pays_commission' => true],
            ['name' => 'Globe Homes Ltd', 'user_id' => '15868888', 'seeds' => ['18417795', '18422131', '18408753'], 'pays_commission' => false],
            ['name' => 'Halfrome LTD', 'user_id' => '21447935', 'seeds' => ['17943270', '18168058', '17746059'], 'pays_commission' => false],
            ['name' => 'Instabook Ltd', 'user_id' => '20618506', 'seeds' => ['16669501', '17657782', '16980272'], 'pays_commission' => true],
            ['name' => 'KEY2STAY LTD', 'user_id' => '24113633', 'seeds' => ['18426501', '18412673', '18415087'], 'pays_commission' => false],
            ['name' => 'Kish', 'user_id' => '8593186', 'seeds' => ['12663760', '18150746'], 'pays_commission' => true],
            ['name' => 'My Place Properties', 'user_id' => '22116668', 'seeds' => ['18376013', '16791329'], 'pays_commission' => true],
            ['name' => 'PPM', 'user_id' => '890512', 'seeds' => ['18427211'], 'pays_commission' => true],
            ['name' => 'SilverLine Rooms', 'user_id' => '20889927', 'seeds' => ['18410802', '18380379'], 'pays_commission' => true],
            ['name' => 'UK LONDON FLAT', 'user_id' => '20824796', 'seeds' => ['18422285', '18402638', '18354956'], 'pays_commission' => true],
            ['name' => 'Urban Base Properties', 'user_id' => '13002775', 'seeds' => ['18425505', '18423497', '18423460'], 'pays_commission' => true],
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

    // Javier's own vacancy sheet (JMS and FENIX). Only MOVE OUT and APT BREAK
    // rows are offered; the tenant columns (O, P, T) are never read.
    'javier' => [
        'spreadsheet_id' => env('JAVIER_SHEET_ID', '1G-BmcFIet1yM-FAWFGOWiVO5HK6H7Rq5bePo-EQBYk8'),
        'tab' => env('JAVIER_SHEET_TAB', 'ROOMS'),
        'cache_timeout' => env('JAVIER_CACHE_TIMEOUT', 900),
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
