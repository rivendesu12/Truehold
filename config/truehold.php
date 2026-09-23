<?php

return [

    // The agents who sign sourcing agreements, offered in the "Your name"
    // dropdown. Anyone can still type another name. Override with
    // TRUEHOLD_AGENTS="Alex,Emanuela,..." without a deploy.
    'agents' => array_values(array_filter(array_map('trim', explode(',',
        env('TRUEHOLD_AGENTS', 'Alex,Emanuela,Mohammed,Oana,Pasquale,Giacomo')
    )))),

    // Partner agencies' bank details (AgencyBankDetails). A private file on
    // the server: the repository is public.
    'agency_bank_details' => env('AGENCY_BANK_DETAILS', storage_path('app/private/agency-bank-details.json')),

];
