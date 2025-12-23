<?php

namespace App\Services\Tiktok;

class TiktokSignature
{
    public static function generate(array $params, string $appSecret): string
    {
        ksort($params);

        $str = '';
        foreach ($params as $key => $value) {
            $str .= $key . $value;
        }

        return hash_hmac('sha256', $str, $appSecret);
    }
}
