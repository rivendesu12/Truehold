<?php

/**
 * London transport facts and the vocabulary that maps onto them.
 *
 * The assistant is not asked to know London. It maps the words an agent types
 * onto the values defined here, and the matching is done in our code against
 * real TfL data (transport:build-stations, transport:build-journeys).
 */
return [

    'walking' => [
        // 3 mph. Used to turn a straight-line distance into a walk estimate.
        'minutes_per_mile' => 20,
        // "Close to a tube", "good transport links", "near a station" — the
        // threshold an agent means by those, in walking minutes.
        'near_station_minutes' => 10,
        // Beyond this a station is not the listing's station in any useful sense.
        'max_walk_miles' => 1.5,
    ],

    'index' => [
        // National Rail in TfL's data is the whole country: Avanti reaches
        // Glasgow, CrossCountry Aberdeen. Only the commuter belt can ever be
        // a London listing's nearest station, and an index of 2,000 stations
        // makes every nearest-station search slower for no gain.
        'centre' => [51.5074, -0.1278], // Charing Cross
        'max_miles_from_centre' => 30,
    ],

    'journeys' => [
        // Journey times are built for a weekday morning commute arrival, which
        // is the worst realistic case and the one tenants care about.
        'arrive_by' => '0830',
        // Modes a tenant would actually use to commute.
        'modes' => 'tube,dlr,overground,elizabeth-line,tram,national-rail,walking',
        // Recomputed rarely: tube times only move when the network does.
        'rebuild_after_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Destination hubs
    |--------------------------------------------------------------------------
    |
    | Journey times are precomputed from every listing's nearest station to
    | each hub below, so "30 minutes from Bond Street" is a real number.
    | `station` must match a name in storage/app/transport/stations.json.
    | `aliases` are what agents and tenants actually say — areas, employers
    | and universities are mapped to the station that serves them.
    |
    */
    'hubs' => [
        'bond-street' => [
            'label' => 'Bond Street',
            'station' => 'Bond Street',
            'aliases' => ['west end', 'mayfair', 'oxford street', 'selfridges', 'marylebone village'],
        ],
        'oxford-circus' => [
            'label' => 'Oxford Circus',
            'station' => 'Oxford Circus',
            'aliases' => ['soho', 'fitzrovia', 'regent street', 'central london', 'the centre', 'town'],
        ],
        'liverpool-street' => [
            'label' => 'Liverpool Street',
            'station' => 'Liverpool Street',
            'aliases' => ['the city', 'city of london', 'spitalfields', 'broadgate', 'square mile'],
        ],
        'bank' => [
            'label' => 'Bank',
            'station' => 'Bank',
            'aliases' => ['monument', 'cheapside', 'threadneedle'],
        ],
        'canary-wharf' => [
            'label' => 'Canary Wharf',
            'station' => 'Canary Wharf',
            'aliases' => ['the wharf', 'docklands', 'isle of dogs', 'hsbc', 'barclays', 'citi', 'jpmorgan'],
        ],
        'kings-cross' => [
            'label' => "King's Cross",
            'station' => "King's Cross St. Pancras",
            'aliases' => ['kings cross', "king's cross", 'st pancras', 'granary square', 'google', 'central saint martins'],
        ],
        'euston' => [
            'label' => 'Euston',
            'station' => 'Euston',
            'aliases' => ['ucl', 'university college london', 'bloomsbury'],
        ],
        'holborn' => [
            'label' => 'Holborn',
            'station' => 'Holborn',
            'aliases' => ['covent garden', 'lse', 'london school of economics', 'chancery lane', 'midtown'],
        ],
        'london-bridge' => [
            'label' => 'London Bridge',
            'station' => 'London Bridge',
            'aliases' => ['borough', 'bermondsey', 'the shard', 'guys hospital', "guy's hospital", 'southwark'],
        ],
        'waterloo' => [
            'label' => 'Waterloo',
            'station' => 'Waterloo',
            'aliases' => ['south bank', 'kings college', "king's college", 'southbank', 'lambeth'],
        ],
        'victoria' => [
            'label' => 'Victoria',
            'station' => 'Victoria',
            'aliases' => ['westminster', 'pimlico', 'belgravia', 'whitehall'],
        ],
        'old-street' => [
            'label' => 'Old Street',
            'station' => 'Old Street',
            'aliases' => ['shoreditch', 'hoxton', 'silicon roundabout', 'tech city', 'clerkenwell'],
        ],
        'stratford' => [
            'label' => 'Stratford',
            'station' => 'Stratford',
            'aliases' => ['westfield stratford', 'olympic park', 'queen elizabeth park', 'ucl east'],
        ],
        'paddington' => [
            'label' => 'Paddington',
            'station' => 'Paddington',
            'aliases' => ['bayswater', 'st marys hospital', "st mary's hospital", 'heathrow express'],
        ],
        'hammersmith' => [
            'label' => 'Hammersmith',
            'station' => 'Hammersmith (Dist&Picc Line)',
            'aliases' => ['west london', 'fulham', 'chiswick', 'imperial college west'],
        ],
        'shepherds-bush' => [
            'label' => "Shepherd's Bush",
            'station' => "Shepherd's Bush (Central)",
            'aliases' => ['westfield', 'white city', 'bbc', 'imperial white city'],
        ],
        'camden-town' => [
            'label' => 'Camden Town',
            'station' => 'Camden Town',
            'aliases' => ['camden', 'kentish town', 'chalk farm'],
        ],
        'angel' => [
            'label' => 'Angel',
            'station' => 'Angel',
            'aliases' => ['islington', 'upper street'],
        ],
        'brixton' => [
            'label' => 'Brixton',
            'station' => 'Brixton',
            'aliases' => ['south london', 'clapham', 'streatham'],
        ],
        'south-kensington' => [
            'label' => 'South Kensington',
            'station' => 'South Kensington',
            'aliases' => ['imperial college', 'chelsea', 'kensington', 'knightsbridge', 'museums'],
        ],
        'heathrow' => [
            'label' => 'Heathrow',
            'station' => 'Heathrow Terminals 2 & 3',
            'aliases' => ['the airport', 'lhr', 'heathrow airport'],
        ],
        'canada-water' => [
            'label' => 'Canada Water',
            'station' => 'Canada Water',
            'aliases' => ['rotherhithe', 'surrey quays', 'south east london'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Zone meaning
    |--------------------------------------------------------------------------
    |
    | Shown to the assistant so it never invents its own definition, and used
    | in the panel to explain a result. Zones come from TfL per station.
    |
    */
    'zones' => [
        1 => 'Central London — the West End and the City.',
        2 => 'Inner London — the ring just outside the centre.',
        3 => 'Inner suburbs, typically 20–35 minutes into the centre.',
        4 => 'Outer suburbs, typically 30–45 minutes into the centre.',
        5 => 'Outer London, typically 40–55 minutes into the centre.',
        6 => 'The edge of London, including Heathrow.',
    ],

];
