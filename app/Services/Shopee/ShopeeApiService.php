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
        $timestamp = time();
        $path      = "/api/v2/shop/get_shop_info";

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
        $timestamp = time();
        $path      = "/api/v2/product/get_item_list";

        $baseString = $this->partnerId . $path . $timestamp . $accessToken . $shopId;
        $sign = hash_hmac('sha256', $baseString, $this->partnerKey);
        $url = "{$this->host}{$path}"."?partner_id={$this->partnerId}&timestamp={$timestamp}&sign={$sign}&access_token={$accessToken}&shop_id={$shopId}&item_status=NORMAL&page_size={$pageSize}&offset={$offset}";

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

    public function getOrder(string $accessToken, int $shopId, string $timeFrom, string $timeTo, int $pageSize = 20, string $orderStatus = 'READY_TO_SHIP', string $timeRange = 'create_time', ?string $cursor = '')
    {
        $timestamp  = time();
        $path       = '/api/v2/order/get_order_list';
        $baseString = $this->partnerId . $path . $timestamp . $accessToken . $shopId;
        $sign       = hash_hmac('sha256', $baseString, $this->partnerKey);

        $add_parameter = '';
        if (!empty($cursor)) {
            $add_parameter = "&cursor={$cursor}";
        }

        $url = "{$this->host}{$path}"."?timestamp={$timestamp}&partner_id={$this->partnerId}&sign={$sign}&access_token={$accessToken}&shop_id={$shopId}&time_from={$timeFrom}&time_to={$timeTo}&time_range_field={$timeRange}&page_size={$pageSize}&order_status={$orderStatus}{$add_parameter}";

        $response = Http::withHeaders([
            "Content-Type" => "application/json"
        ])->get($url);

        return $response->json();
    }

    public function getOrderDetail(string $accessToken, int $shopId, string $orderSn)
    {
        $timestamp = time();
        $path      = "/api/v2/order/get_order_detail";

        $baseString = $this->partnerId . $path . $timestamp . $accessToken . $shopId;
        $sign = hash_hmac('sha256', $baseString, $this->partnerKey);
        $url = "{$this->host}{$path}"."?timestamp={$timestamp}&partner_id={$this->partnerId}&sign={$sign}&access_token={$accessToken}&shop_id={$shopId}&order_sn_list={$orderSn}&response_optional_fields=buyer_user_id,buyer_username,estimated_shipping_fee,recipient_address,actual_shipping_fee,goods_to_declare,note,note_update_time,item_list,pay_time,dropshipper,dropshipper_phone,split_up,buyer_cancel_reason,cancel_by,cancel_reason,actual_shipping_fee_confirmed,buyer_cpf_id,fulfillment_flag,pickup_done_time,package_list,shipping_carrier,payment_method,total_amount,buyer_username,invoice_data,order_chargeable_weight_gram,return_request_due_date,edt,payment_info";

        $response = Http::withHeaders([
            "Content-Type" => "application/json"
        ])->get($url);

        return $response->json();
    }

    public function getEscrowDetail(string $accessToken, int $shopId, string $orderSn)
    {
        // commision_fee = biaya admin, delivery_seller_protection_fee_premium_amount = premi, service_fee = biaya layanan, seller_order_processing_fee = biaya proses pesanan, voucher_from_seller = voucher penjual
        $timestamp = time();
        $path      = "/api/v2/payment/get_escrow_detail";

        $baseString = $this->partnerId . $path . $timestamp . $accessToken . $shopId;
        $sign = hash_hmac('sha256', $baseString, $this->partnerKey);
        $url = "{$this->host}{$path}"."?partner_id={$this->partnerId}&timestamp={$timestamp}&sign={$sign}&access_token={$accessToken}&shop_id={$shopId}&order_sn={$orderSn}";

        $response = Http::withHeaders([
            "Content-Type" => "application/json"
        ])->get($url);

        return $response->json();
    }

    public function getTrackingNumber(string $accessToken, int $shopId, string $orderSn)
    {
        $timestamp = time();
        $path      = "/api/v2/logistics/get_tracking_number";

        $baseString = $this->partnerId . $path . $timestamp . $accessToken . $shopId;
        $sign = hash_hmac('sha256', $baseString, $this->partnerKey);
        $url = "{$this->host}{$path}"."?partner_id={$this->partnerId}&timestamp={$timestamp}&sign={$sign}&access_token={$accessToken}&shop_id={$shopId}&order_sn={$orderSn}";

        $response = Http::withHeaders([
            "Content-Type" => "application/json"
        ])->get($url);

        return $response->json();
    }
}
