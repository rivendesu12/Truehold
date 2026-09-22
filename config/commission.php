<?php

/**
 * What each agency pays us, so "prioritise commission" can rank by money
 * rather than by a yes/no flag.
 *
 * The feed's `paying` column says whether an agency pays at all; it does not
 * say how much. Until the real figures are entered below, a paying agency is
 * valued at `default_percent` and the estimate is labelled as an estimate
 * wherever it is shown — a guessed number must never be presented as a fact.
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

    // Applied to an agency that pays but has no rate entered yet.
    'default_percent' => 50,

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
