<?php

namespace Tests\Unit;

// use PHPUnit\Framework\TestCase;
use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Services\Shopee\ShopeeApiService;
use App\Services\Shopee\ShopeeSignature;

class ShopeeApiServiceTest extends TestCase
{
    public function test_get_shop_info_success()
    {
        putenv('SHOPEE_PARTNER_ID=12345');
        putenv('SHOPEE_PARTNER_KEY=secretkey');
        putenv('SHOPEE_HOST=https://partner.shopeemobile.com');

        Http::fake([
            '*' => Http::response([
                'response' => [
                    'shop_name' => 'Test Shop'
                ]
            ], 200)
        ]);

        // inject dependency
        $signature = new ShopeeSignature();

        $service = new ShopeeApiService($signature);

        $result = $service->getShopInfo(
            'fake-token',
            999
        );

        Http::assertSent(function ($request) {
        return str_contains($request->url(), 'get_shop_info');
        });

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && str_contains($request->url(), 'get_shop_info');
        });

        $this->assertEquals('Test Shop', $result['response']['shop_name']);
    }

    public function test_get_shop_info_failed()
    {
        putenv('SHOPEE_PARTNER_KEY=secretkey');
        putenv('SHOPEE_HOST=https://partner.shopeemobile.com');

        Http::fake([
            '*' => Http::response([
                'request_id' => '1224egnjfnr',
                'error'      => 'error_auth',
                'message'    => 'invalid partner_id or shop_id'
            ], 401)
        ]);

        $signature = new ShopeeSignature();
        $service = new ShopeeApiService($signature);

        $result = $service->getShopInfo('fake-token', 999);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'get_shop_info');
        });

        Http::assertSent(function ($request) {
            return $request->method() === 'GET' && str_contains($request->url(), 'get_shop_info');
        });

        $this->assertEquals('error_auth', $result['error']);
        $this->assertEquals('invalid partner_id or shop_id', $result['message']);
    }
}
