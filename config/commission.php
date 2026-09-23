<?php

/**
 * What each agency pays us, so "prioritise commission" can rank by money
 * rather than by a yes/no flag.
 *
 * The feed's `paying` column says whether an agency pays at all; it does not
 * say how much. Until the real figures are entered below, a paying agency is
 * flagged as paying and no amount is shown — a guessed number must never be
 * presented as a fact, and an agent reading a made-up fee is worse off than
 * one reading none.
 *
 * Rates are keyed by a normalised agency name: lowercased, with Ltd/Limited
 * and punctuation removed and spaces collapsed, so "Cloudrooms Ltd", "Cloud
 * Rooms" and "CLOUDROOMS LTD" are all `cloudrooms`. Use `php artisan
 * commission:agencies` to list the keys the live feed actually produces.
 *
 * `type` is either:
 *   percent — a share of the first month's rent
 *   fixed   — a flat fee in pounds per let
 */
return [

    // No invented figures. With no default, an agency that pays is shown as
    // paying and nothing more — which is all the feed actually tells us.
    // Enter real rates below and the pound values appear on their own.
    'default_percent' => 0,

    /*
    |--------------------------------------------------------------------------
    | Agencies that pay, regardless of the feed
    |--------------------------------------------------------------------------
    |
    | The feed's `paying` column is only filled for its own SpareRoom rows.
    | Every spreadsheet-sourced row has it blank, so our own suppliers could
    | never appear in a commission search even though they are precisely the
    | agencies we have arrangements with. Normalised agency keys listed here
    | count as paying whatever the feed says.
    |
    | Use `php artisan commission:agencies` to see the keys in the live feed.
    |
    */
    'always_pay' => [
        'javier',   // Javier (JMS), Javier (FENIX) and the supplier sheet's "Javier"
        'jms',
        'fenix',
        'banksia',
        'soreva',
    ],

    /*
    |--------------------------------------------------------------------------
    | Agencies that never pay, whatever the feed says
    |--------------------------------------------------------------------------
    |
    | The feed marks some agencies as paying that do not. This wins over both
    | the feed and always_pay.
    |
    */
    'never_pay' => [
        'instabook',
    ],

    // Real figures go here. Delete a line to fall back to the default.
    // Example:
    //   'banksia rooms' => ['type' => 'percent', 'value' => 100],
    //   'built asset managment' => ['type' => 'fixed', 'value' => 400],
    'rates' => [
        //
    ],

    // Spellings of the same agency that should share one rate. Left is the
    // normalised variant, right is the normalised key it maps to.
    'aliases' => [
        'cloud rooms' => 'cloudrooms',
        'cloudrooms' => 'cloudrooms',
        'silverline rooms' => 'silverline rooms',
        'ap' => 'ap horizon',
        'horizon' => 'ap horizon',
        'ap horizon' => 'ap horizon',
    ],

];
