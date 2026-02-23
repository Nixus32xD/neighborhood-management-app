<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CC2 Construction Index Rate
    |--------------------------------------------------------------------------
    |
    | Base monthly rate for CC2 fines, based on Mendoza provincial
    | construction index.
    | Month 1-2: this rate
    | Month 3+: double this rate
    |
    */
    'cc2_construction_index_rate' => (float) env('CC2_CONSTRUCTION_INDEX_RATE', 0.03),
];

