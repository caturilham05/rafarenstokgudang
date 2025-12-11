<?php

namespace App\Services\Shopee;

class ShopeeSignature
{
    /**
     * Generate Shopee signature.
     */
    public function make(int $partnerId, string $partnerKey , string $path, int $timestamp)
    {
        $baseString = sprintf("%s%s%s", $partnerId, $path, $timestamp);
        return hash_hmac('sha256', $baseString, $partnerKey);
    }
}
