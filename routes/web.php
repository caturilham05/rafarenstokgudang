<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/shopee_redirect_auth_demo', function() {
    $path = "/api/v2/shop/auth_partner";
    $redirectUrl = "http://demo.rafarenstokgudang.com/";
    $timest = time();
    $baseString = sprintf("%s%s%s", env('SHOPEE_PARTNER_ID_TEST'), $path, $timest);
    $sign = hash_hmac('sha256', $baseString, env('SHOPEE_PARTNER_KEY_TEST'));
    $url = sprintf("%s%s?timestamp=%s&partner_id=%s&sign=%s&redirect=%s", env('SHOPEE_REDIRECT_URL_TEST'), $path, $timest, env('SHOPEE_PARTNER_ID_TEST'), $sign, $redirectUrl);
    return $url;
});
