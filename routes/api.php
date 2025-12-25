<?php

use Illuminate\Support\Facades\Route;

Route::post('/shopee/webhook', [App\Http\Controllers\ShopeeWebhookController::class, 'handle']);
Route::post('/tiktok/webhook', [App\Http\Controllers\TiktokWebhookController::class, 'handle']);
