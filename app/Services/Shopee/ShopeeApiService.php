<?php

namespace App\Services\Shopee;

use Illuminate\Support\Facades\Http;

class ShopeeApiService
{
    protected $signature;
    protected $partnerId;
    protected $partnerKey;
    protected $host;

    public function __construct(ShopeeSignature $signature)
    {
        $this->signature = $signature;
        $this->partnerId  = env('SHOPEE_PARTNER_ID');
        $this->partnerKey = env('SHOPEE_PARTNER_KEY');
        $this->host       = env('SHOPEE_HOST');
    }

    /**
     * Contoh request ke Shopee API
     * misalnya: get merchant info / shop info
     */
    public function getShopInfo($accessToken, $shopId)
    {
        $timestamp  = time();
        $path = "/api/v2/shop/get_shop_info";

        $baseString = $this->partnerId . $path . $timestamp . $accessToken . $shopId;
        $sign = hash_hmac('sha256', $baseString, $this->partnerKey);

        $url = "{$this->host}{$path}"."?partner_id={$this->partnerId}&timestamp={$timestamp}&sign={$sign}&access_token={$accessToken}&shop_id={$shopId}";

        $response = Http::withHeaders([
            "Content-Type" => "application/json"
        ])->get($url);
        return $response->json();
    }

    public function getProducts(string $accessToken, int $shopId, int $offset = 0, int $pageSize = 10)
    {
        $timestamp  = time();
        $path = "/api/v2/product/get_item_list";

        $baseString = $this->partnerId . $path . $timestamp . $accessToken . $shopId;
        $sign = hash_hmac('sha256', $baseString, $this->partnerKey);
        $url = "{$this->host}{$path}"."?partner_id={$this->partnerId}&timestamp={$timestamp}&sign={$sign}&access_token={$accessToken}&shop_id={$shopId}&item_status=NORMAL&item_status=BANNED&page_size={$pageSize}&offset={$offset}";

        // #Refactoring to get detailed info and models
        // $listResponse = Http::withHeaders([
        //     "Content-Type" => "application/json"
        // ])->get($url);

        //  $listResponse = $listResponse->json();

        // if (!empty($listResponse['error'])) {
        //     return $listResponse;
        // }

        // $items = $listResponse['response']['item'] ?? [];

        // if (empty($items)) {
        //     return [];
        // }

        // $itemIds = array_column($items, 'item_id');
        // $itemIdString = implode(',', $itemIds);

        // $path = '/api/v2/product/get_item_base_info';
        // $baseString = $this->partnerId . $path . $timestamp . $accessToken . $shopId;
        // $sign = hash_hmac('sha256', $baseString, $this->partnerKey);

        // $url = "{$this->host}{$path}"."?partner_id={$this->partnerId}&timestamp={$timestamp}&sign={$sign}&access_token={$accessToken}&shop_id={$shopId}&item_id_list={$itemIdString}";
        // $infoResponse = Http::withHeaders([
        //     "Content-Type" => "application/json"
        // ])->get($url);

        // $infoMap = collect($infoResponse['response']['item_list'] ?? [])
        //     ->keyBy('item_id')
        //     ->toArray();

        // $pathModel = '/api/v2/product/get_model_list';
        // $baseStringModel = $this->partnerId . $pathModel . $timestamp . $accessToken . $shopId;
        // $signModel = hash_hmac('sha256', $baseStringModel, $this->partnerKey);

        // foreach ($listResponse['response']['item'] as $key => $value) {
        //     $item_id = $value['item_id'];
        //     $urlModel = "{$this->host}{$pathModel}"."?partner_id={$this->partnerId}&timestamp={$timestamp}&sign={$signModel}&access_token={$accessToken}&shop_id={$shopId}&item_id={$item_id}";
        //     $modelResponse = Http::withHeaders([
        //         "Content-Type" => "application/json"
        //     ])->get($urlModel);
        //     $listResponse['response']['item'][$key]['product'] = $infoMap[$item_id] ?? [];
        //     $listResponse['response']['item'][$key]['models']  = $modelResponse['response']['model'] ?? [];
        // }

        // return $listResponse;
        // #Refactoring to get detailed info and models

        $response = Http::withHeaders([
            "Content-Type" => "application/json"
        ])->get($url);

        $response_items = $response->json();
        if (!empty($response_items['error'])) {
            return $response_items;
        }

        if (empty($response_items['response']['item'])) {
            return $response_items;
        }

        $path = '/api/v2/product/get_item_base_info';
        $baseString = $this->partnerId . $path . $timestamp . $accessToken . $shopId;
        $sign = hash_hmac('sha256', $baseString, $this->partnerKey);

        $pathModel = '/api/v2/product/get_model_list';
        $baseStringModel = $this->partnerId . $pathModel . $timestamp . $accessToken . $shopId;
        $signModel = hash_hmac('sha256', $baseStringModel, $this->partnerKey);

        foreach ($response_items['response']['item'] as $key => $item) {
            $url = "{$this->host}{$path}"."?partner_id={$this->partnerId}&timestamp={$timestamp}&sign={$sign}&access_token={$accessToken}&shop_id={$shopId}&item_id_list=" . $item['item_id'];
            $response_detail = Http::withHeaders([
                "Content-Type" => "application/json"
            ])->get($url);
            $response_items['response']['item'][$key]['product'] = $response_detail['response']['item_list'][0] ?? [];

            $urlModel = "{$this->host}{$pathModel}"."?partner_id={$this->partnerId}&timestamp={$timestamp}&sign={$signModel}&access_token={$accessToken}&shop_id={$shopId}&item_id=" . $item['item_id'];
            $response_model = Http::withHeaders([
                "Content-Type" => "application/json"
            ])->get($urlModel);

            if (!empty($response_model['response']['model'])) {
                foreach ($response_model['response']['model'] as $key_model => $model) {
                    $response_items['response']['item'][$key]['models'][$key_model] = $model;
                }
            } else {
                $response_items['response']['item'][$key]['models'] = [];
            }
        }

        return $response_items;
    }
}
