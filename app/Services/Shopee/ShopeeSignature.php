<?php

namespace App\Services\Shopee;

class ShopeeSignature
{
    /**
     * Generate Shopee signature.
     */
    public function make(string $partnerKey, string $path, int $timestamp, string $body = '')
    {
        $baseString = $partnerKey . $path . $timestamp . $body;
        return hash_hmac('sha256', $baseString, $partnerKey);
    }
}
