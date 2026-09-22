<?php

return [

    // The agents who sign sourcing agreements, offered in the "Your name"
    // dropdown. Anyone can still type another name. Override with
    // TRUEHOLD_AGENTS="Alex,Emanuela,..." without a deploy.
    'agents' => array_values(array_filter(array_map('trim', explode(',',
        env('TRUEHOLD_AGENTS', 'Alex,Emanuela,Mohammed,Oana,Pasquale,Giacomo')
    )))),

];
