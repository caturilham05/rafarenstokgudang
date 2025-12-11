<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Schedule::command('shopee:refresh-tokens')->everyMinute();

Schedule::call(function () {
    Artisan::call('shopee:refresh-tokens');
})->everyTwoHours();

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote');
