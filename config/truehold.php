<?php

return [

    // The agents who sign sourcing agreements, offered in the "Your name"
    // dropdown. Anyone can still type another name. Override with
    // TRUEHOLD_AGENTS="Alex,Emanuela,..." without a deploy.
    'agents' => array_values(array_filter(array_map('trim', explode(',',
        env('TRUEHOLD_AGENTS', 'Alex,Emanuela,Mohammed,Oana,Pasquale,Giacomo')
    )))),

    // Our own "Room targets" sheet, which Sigou hands out on request. Built
    // from the sheet the site already reads, so no id lives in this public repo.
    'room_targets_url' => env('ROOM_TARGETS_URL') ?: (env('SUPPLIER_TARGETS_SHEET_ID')
        ? 'https://docs.google.com/spreadsheets/d/' . env('SUPPLIER_TARGETS_SHEET_ID') . '/edit' : null),

    // Partner agencies' bank details (AgencyBankDetails). A private file on
    // the server: the repository is public.
    'agency_bank_details' => env('AGENCY_BANK_DETAILS', storage_path('app/private/agency-bank-details.json')),

    // What Sigou knows about the office crew (App\Support\SigouOffice),
    // from the team's WhatsApp groups. A private file on the server.
    'sigou_office' => env('SIGOU_OFFICE', storage_path('app/private/sigou-office.json')),

];
