<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Administrator Account
    |--------------------------------------------------------------------------
    |
    | The e-mail address that identifies the application administrator. The
    | database seeder promotes this account when it exists and creates it when
    | it does not. The password is only used while creating a new account.
    |
    */

    'email' => env('ADMIN_EMAIL'),

    'name' => env('ADMIN_NAME', 'Admin'),

    'password' => env('ADMIN_PASSWORD'),

];
