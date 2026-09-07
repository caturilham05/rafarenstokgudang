<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ProductMaster;
use App\Models\ProductMasterItem;
use App\Models\Store;
use App\Services\Shopee\ShopeeApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShopeeWebhookController extends Controller
{
    private function resetShopeeLogIfNeeded()
    {
        $path = storage_path('logs/shopee.log');

        if (!file_exists($path)) {
            return;
        }

        $maxSize  = 10 * 1024 * 1024;  // 5 MB dalam bytes
        $fileSize = filesize($path);

        if ($fileSize >= $maxSize) {
            file_put_contents($path, '');  // kosongkan file
        }
    }

    public function handle(Request $request)
    {
          // Tangani webhook dari Shopee di sini
        $data = $request->all();
        $code = $data['code'] ?? 0;
        if ($code == 0) {
            Log::channel('shopee')->info('Received Shopee Webhook', $data);
            return response()->json(['status' => 'no code found', 'data' => $data]);
        }

        switch ($code) {
            case '22':
                $this->handleProductUpdate($data);
                break;

            case '3':
                $a = $this->handleOrderDetail($data);
                break;

            case '4':
                $this->handleTrackingNumber($data);
                break;

            case '29':
                $this->handleReturn($data);
                break;

            default:
                  # code...
                break;
        }

          // Reset jika sudah lebih dari 5MB
        $this->resetShopeeLogIfNeeded();

          // Tulis log ke file khusus
        Log::channel('shopee')->info('Received Shopee Webhook', $data);

          // Lakukan proses sesuai kebutuhan, misalnya memperbarui status pesanan, inventaris, dll.

        return response()->json(['status' => 'success']);
    }

    public function handleOrderDetail($data)
    {
        $order_sn = $data['data']['ordersn'] ?? null;
        $status   = $data['data']['status'] ?? null;
        $shopId   = $data['shop_id'] ?? null;

        try {

            // ===============================
            // VALIDASI TOKO (NO TRANSACTION)
            // ===============================
            $store = Store::getStores($shopId)->first();
            if (!$store) {
                throw new \Exception('Toko tidak ditemukan');
            }

            $apiService  = app(ShopeeApiService::class);
            $accessToken = $store->access_token;

            $order = [];
            $escrow = [];

            // ===============================
            // API CALL DI LUAR TRANSACTION
            // ===============================
            if ($status === 'READY_TO_SHIP') {

                $response = $apiService->getOrderDetail($accessToken, $shopId, $order_sn);
                if (!empty($response['error'])) {
                    throw new \Exception('Error fetch marketplace');
                }

                $order = $response['response']['order_list'][0] ?? [];

                $responseEscrow = $apiService->getEscrowDetail($accessToken, $shopId, $order_sn);
                if (!empty($responseEscrow['error'])) {
                    throw new \Exception('Error fetch escrow');
                }

                $escrow = $responseEscrow['response'] ?? [];
            }

            if ($status === 'COMPLETED') {

                $escrowDetail = $apiService->getEscrowDetail(
                    $store->access_token,
                    $store->shop_id,
                    $order_sn
                );

                if (!empty($escrowDetail['error'])) {
                    throw new \Exception($escrowDetail['error']);
                }

                $escrow = $escrowDetail['response'] ?? [];
            }

            // ===============================
            // TRANSACTION DB ONLY
            // ===============================
            DB::transaction(function () use (
                $status,
                $order_sn,
                $order,
                $escrow,
                $store
            ) {

                switch ($status) {

                    case 'READY_TO_SHIP':

                        $recipient = $order['recipient_address'] ?? [];

                        $order_income = $escrow['order_income'] ?? [];
                        if (empty($order_income)) {
                            throw new \Exception('order income belum tersedia');
                        }

                        $escrow_amount_after_adjustment = $order_income['escrow_amount_after_adjustment'] ?? 0;
                        $total_price = $escrow_amount_after_adjustment
                            ?: ($escrow['buyer_payment_info']['buyer_total_amount'] ?? 0);

                        $qty_total = collect($order['item_list'])->sum('model_quantity_purchased');

                        $orderModel = Order::updateOrCreate(
                            ['invoice' => $order_sn],
                            [
                                'invoice'        => $order_sn,
                                'store_id'       => $store->id,
                                'marketplace_name' => $store->marketplace_name,
                                'store_name'     => $store->store_name,
                                'buyer_username' => $order['buyer_username'] ?? null,
                                'customer_name'  => $recipient['name'] ?? null,
                                'customer_phone' => $recipient['phone'] ?? null,
                                'customer_address' => $recipient['full_address'] ?? null,
                                'courier'        => $order['package_list'][0]['shipping_carrier'] ?? null,
                                'qty'            => $qty_total,
                                'shipping_cost'  => $order['estimated_shipping_fee'] ?? 0,
                                'status'         => $order['order_status'] ?? null,
                                'notes'          => $order['message_to_seller'] ?? null,
                                'payment_method' => $order['payment_method'] ?? null,
                                'order_time'     => date('Y-m-d H:i:s', $order['create_time'] ?? time()),
                                'total_price'    => $total_price,
                                'commission_fee' => $order_income['commission_fee'] ?? 0,
                                'delivery_seller_protection_fee_premium_amount' =>
                                    $order_income['delivery_seller_protection_fee_premium_amount'] ?? 0,
                                'service_fee'    => $order_income['service_fee'] ?? 0,
                                'seller_order_processing_fee' =>
                                    $order_income['seller_order_processing_fee'] ?? 0,
                                'voucher_from_seller' => $order_income['voucher_from_seller'] ?? 0,
                            ]
                        );

                        if (!$orderModel->wasRecentlyCreated) {
                            return;
                        }

                        foreach ($order['item_list'] ?? [] as $item) {

                            $product = Product::where('product_online_id', (string) $item['item_id'])
                                ->where('product_model_id', (string) $item['model_id'])
                                ->lockForUpdate()
                                ->first();

                            if (!$product) {
                                continue;
                            }

                            OrderProduct::updateOrCreate(
                                [
                                    'order_id'   => $orderModel->id,
                                    'product_id' => $product->id,
                                ],
                                [
                                    'order_id'          => $orderModel->id,
                                    'product_id'        => $product->id,
                                    'product_online_id' => $product->product_online_id,
                                    'product_model_id'  => $product->product_model_id,
                                    'product_name'      => $product->product_name,
                                    'varian'            => $product->varian,
                                    'qty'               => $item['model_quantity_purchased'],
                                    'sale'              => $item['model_discounted_price']
                                        ?: $item['model_original_price'],
                                ]
                            );
                        }

                        break;

                    case 'PROCESSED':
                    case 'SHIPPED':
                    case 'TO_CONFIRM_RECEIVE':

                        $order_exists = Order::where('invoice', $order_sn)->firstOrFail();
                        $order_exists->update(['status' => $status]);

                        break;

                    case 'COMPLETED':

                        $order_exists = Order::where('invoice', $order_sn)->firstOrFail();

                        $deliveryFee = $escrow['order_income']['delivery_seller_protection_fee_premium_amount'] ?? 0;
                        $escrowAmount = $escrow['order_income']['escrow_amount_after_adjustment'] ?? 0;

                        $total_price = $escrowAmount
                            ?: ($escrow['buyer_payment_info']['buyer_total_amount'] ?? 0);

                        $order_exists->update([
                            'status'      => $status,
                            'total_price' => $total_price - $deliveryFee,
                        ]);

                        break;

                    case 'CANCEL':
                    case 'CANCELLED':

                        $order = Order::with('orderProducts')
                            ->where('invoice', $order_sn)
                            ->lockForUpdate()
                            ->firstOrFail();

                        $order->update(['status' => 'CANCELLED']);

                        break;
                }
            });

            return response()->json(['status' => 'success']);

        } catch (\Throwable $e) {

            Log::channel('shopee')->error($e->getMessage(), [
                'invoice' => $order_sn,
                'status'  => $status,
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function handleProductUpdate($data)
    {
          // {"msg_id":"306af6c3-78f1-40dd-a315-8b83f69390bb","data":{"item_id":25197402630,"model_id":0,"update_field":"original_price","old_value":95197,"new_value":100000,"update_time":1765606277},"shop_id":336094210,"code":22,"timestamp":1765606277}
        $product_online_id = strval($data['data']['item_id'] ?? null);
        $product_model_id  = strval($data['data']['model_id'] ?? null);

        $product = Product::getProducts($product_online_id, $product_model_id)->first();
        if (empty($product)) {
            Log::channel('shopee')->info('Product not found for Shopee Webhook', [
                'product_online_id' => $product_online_id,
                'product_model_id'  => $product_model_id
            ]);
            return response()->json(['status' => 'product not found']);
        }

        $product->sale = $data['data']['new_value'] ?? $product->sale;
        $product->save();

        Log::channel('shopee')->info('Product updated from Shopee Webhook', [
            'product_id'        => $product->id,
            'product_online_id' => $product_online_id,
            'product_model_id'  => $product_model_id,
            'new_sale'          => $product->sale
        ]);
    }

    public function handleTrackingNumber($data)
    {
        $order_sn    = $data['data']['ordersn'] ?? null;
        $tracking_no = $data['data']['tracking_no'] ?? null;

        try {

            DB::transaction(function () use ($order_sn, $tracking_no) {

                $order = Order::where('invoice', $order_sn)->firstOrFail();

                $order->update(['waybill' => $tracking_no]);

            });

            return response()->json(['status' => 'success']);

        } catch (\Throwable $e) {

            Log::channel('shopee')->error($e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function handleReturn($data)
    {
        try {
            $return_sn = $data['data']['return_sn'] ?? null;
            $shopId    = $data['shop_id'] ?? null;

            $store = Store::getStores($shopId)->firstOrFail();

            $apiService = app(ShopeeApiService::class);

            $response = $apiService->getReturnDetail(
                $store->access_token,
                $shopId,
                $return_sn
            );

            if (!empty($response['error'])) {
                throw new \Exception($response['message'] ?? 'Shopee return error');
            }

            $payload = $response['response'];

            DB::transaction(function () use ($payload) {

                $order = Order::where('invoice', $payload['order_sn'])->first();

                OrderReturn::updateOrCreate(
                    ['invoice_return' => $payload['return_sn']],
                    [
                        'order_id'        => $order->id ?? 0,
                        'invoice_order'   => $payload['order_sn'],
                        'invoice_return'  => $payload['return_sn'],
                        'waybill'         => $payload['tracking_number'] ?? null,
                        'buyer_username'  => $payload['user']['username'] ?? null,
                        'courier'         => $payload['reverse_logistics_channel_name'] ?? null,
                        'reason'          => $payload['reason'] ?? null,
                        'reason_text'     => $payload['text_reason'] ?? null,
                        'refund_amount'   => $payload['refund_amount'] ?? null,
                        'return_time'     => !empty($payload['create_time'])
                            ? date('Y-m-d H:i:s', $payload['create_time'])
                            : null,
                        'status'          => $payload['status'] ?? null,
                        'status_logistic' => $payload['logistics_status'] ?? null,
                    ]
                );

            });

            return response()->json(['status' => 'success']);

        } catch (\Throwable $e) {

            Log::channel('shopee')->error($e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
