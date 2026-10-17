<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vercel Runtime
    |--------------------------------------------------------------------------
    |
    | Vercel sets VERCEL=1 on every deployment. The filesystem is read-only
    | except for /tmp, so the application moves storage there when this is on.
    |
    */

    'active' => (bool) env('VERCEL', false),

];
