<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    Artisan::call('shopee:refresh-tokens');
})->everyTwoHours();

// Schedule::call(function () {
//     Artisan::call('shopee:sync-products');
// })->everyTwoHours();
