<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The agency named on the printed sheet
    |--------------------------------------------------------------------------
    |
    | Not the same thing as app.name. That names the system - "IPCR System" is
    | what belongs in a browser tab. This names the hospital, and it goes under
    | "Republic of the Philippines" on the sheet that gets signed and filed.
    |
    | Falls back to the app name so nothing prints blank before it is set.
    |
    */

    'name' => env('AGENCY_NAME', env('APP_NAME', 'Laravel')),

    /*
     * What people actually call it. The full name goes on the printed sheet
     * because that is what a signed record needs; on screen it is a mouthful,
     * and every sentence carrying it twice reads as a form letter.
     */
    'short_name' => env('AGENCY_SHORT_NAME', env('AGENCY_NAME', env('APP_NAME', 'Laravel'))),

    /*
     * Optional second line: the office address, as it appears on letterhead.
     * Left out of the sheet entirely when it is not set.
     */
    'address' => env('AGENCY_ADDRESS'),

];
