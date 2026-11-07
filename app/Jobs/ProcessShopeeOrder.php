<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Store;
use App\Services\Shopee\ShopeeApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ProcessShopeeOrder implements ShouldQueue
{
    use Queueable;

    public $tries = 5;
    public $backoff = [30, 60, 120, 300];

    public function __construct(public int $storeId, public string $orderSn)
    {
        $this->onQueue('shopee');
    }

    public function handle(ShopeeApiService $api): void
    {
        $store = Store::findOrFail($this->storeId);
        $response = $api->getOrderDetail($store->access_token, $store->shop_id, $this->orderSn);
        if (!empty($response['error'])) {
            throw new \RuntimeException($response['message'] ?? $response['error']);
        }
        $order = $response['response']['order_list'][0] ?? null;
        if (!$order || Order::where('invoice', $this->orderSn)->exists()) {
            return;
        }

        $escrow = $api->getEscrowDetail($store->access_token, $store->shop_id, $this->orderSn);
        if (!empty($escrow['error'])) {
            throw new \RuntimeException($escrow['message'] ?? $escrow['error']);
        }
        $income = $escrow['response']['order_income'] ?? [];
        if (!$income) {
            throw new \RuntimeException('Shopee order income belum tersedia');
        }
        $tracking = $api->getTrackingNumber($store->access_token, $store->shop_id, $this->orderSn);
        if (!empty($tracking['error'])) {
            throw new \RuntimeException($tracking['message'] ?? $tracking['error']);
        }

        DB::transaction(function () use ($store, $order, $income, $escrow, $tracking) {
            $recipient = $order['recipient_address'] ?? [];
            $items = $order['item_list'] ?? [];
            $orderModel = Order::firstOrCreate(['invoice' => $this->orderSn], [
                'store_id' => $store->id,
                'marketplace_name' => $store->marketplace_name,
                'store_name' => $store->store_name,
                'buyer_username' => $order['buyer_username'] ?? null,
                'customer_name' => $recipient['name'] ?? null,
                'customer_phone' => $recipient['phone'] ?? null,
                'customer_address' => $recipient['full_address'] ?? null,
                'courier' => $order['package_list'][0]['shipping_carrier'] ?? null,
                'qty' => collect($items)->sum('model_quantity_purchased'),
                'shipping_cost' => $order['estimated_shipping_fee'] ?? 0,
                'status' => $order['order_status'] ?? null,
                'notes' => $order['message_to_seller'] ?? null,
                'payment_method' => $order['payment_method'] ?? null,
                'order_time' => date('Y-m-d H:i:s', $order['create_time'] ?? time()),
                'total_price' => $income['escrow_amount_after_adjustment'] ?? ($escrow['response']['buyer_payment_info']['buyer_total_amount'] ?? 0),
                'commission_fee' => $income['commission_fee'] ?? 0,
                'delivery_seller_protection_fee_premium_amount' => $income['delivery_seller_protection_fee_premium_amount'] ?? 0,
                'service_fee' => $income['service_fee'] ?? 0,
                'seller_order_processing_fee' => $income['seller_order_processing_fee'] ?? 0,
                'voucher_from_seller' => $income['voucher_from_seller'] ?? 0,
                'waybill' => $tracking['response']['tracking_number'] ?? null,
            ]);

            if (!$orderModel->wasRecentlyCreated) {
                return;
            }

            foreach ($items as $item) {
                $product = Product::where('product_online_id', (string) ($item['item_id'] ?? ''))
                    ->where('product_model_id', (string) ($item['model_id'] ?? ''))
                    ->lockForUpdate()->first();
                if (!$product) {
                    continue;
                }
                OrderProduct::updateOrCreate(['order_id' => $orderModel->id, 'product_id' => $product->id], [
                    'order_id' => $orderModel->id,
                    'product_id' => $product->id,
                    'product_online_id' => $product->product_online_id,
                    'product_model_id' => $product->product_model_id,
                    'product_name' => $product->product_name,
                    'varian' => $product->varian,
                    'qty' => $item['model_quantity_purchased'] ?? 0,
                    'sale' => $item['model_discounted_price'] ?: ($item['model_original_price'] ?? 0),
                ]);
            }
        });
    }
}
