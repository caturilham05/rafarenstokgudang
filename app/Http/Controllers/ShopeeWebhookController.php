<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
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

        $maxSize = 10 * 1024 * 1024; // 5 MB dalam bytes
        $fileSize = filesize($path);

        if ($fileSize >= $maxSize) {
            file_put_contents($path, ''); // kosongkan file
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
                $this->handleOrderDetail($data);
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
        // live {"msg_id":"64ca21a145e29bda3a6e31203c800e00","data":{"completed_scenario":null,"items":[],"ordersn":"251214B9XAB0ST","status":"READY_TO_SHIP","update_time":1765689439},"shop_id":336094210,"code":3,"timestamp":1765689440}
        // demo {"msg_id":"85bb37f009e143af84852e17d50b572d","data":{"completed_scenario":null,"items":[],"ordersn":"2512125BFTQGKJ","status":"READY_TO_SHIP","update_time":1736323997},"shop_id":226242306,"code":3,"timestamp":1736323998}

        DB::beginTransaction();
        try {
            $order_sn = $data['data']['ordersn'] ?? null;
            $status = $data['data']['status'] ?? null;
            $shopId = $data['shop_id'] ?? null;

            $store = Store::getStores($shopId)->first();
            if (is_null($store)) {
                Log::channel('shopee')->info('Toko tidak ditemukan');
                return response()->json(['status' => 'Toko tidak ditemukan']);
            }

            $accessToken = $store->access_token;
            switch ($status) {
                case 'READY_TO_SHIP':
                    $apiService = app(ShopeeApiService::class);
                    $response = $apiService->getOrderDetail($accessToken, $shopId, $order_sn);
                    if (!empty($response['error']))
                    {
                        Log::channel('shopee')->info('Error fetch marketplace.');
                        return response()->json(['status' => 'error fetch marketplace']);
                    }

                    $order = $response['response']['order_list'][0] ?? [];
                    $recipient = $order['recipient_address'] ?? [];

                    $response_escrow = $apiService->getEscrowDetail($accessToken, $shopId, $order_sn);
                    if (!empty($response_escrow['error']))
                    {
                        Log::channel('shopee')->info('Error fetch escrow.');
                        return response()->json(['status' => 'error fetch escrow']);
                    }

                    $total_price = $response_escrow['response']['order_income']['escrow_amount_after_adjustment'] ?? 0;
                    // $total_price_final = floor($total_price - ($total_price * 0.005));
                    $total_price_final = $total_price;

                    $order_proccessed = Order::updateOrCreate(
                        [
                            'invoice' => $order_sn,
                        ],
                        [
                            'invoice' => $order_sn,
                            'store_id' => $store ? $store->id : 0,
                            'buyer_username' => $order['buyer_username'],
                            'customer_name' => $recipient['name'],
                            'customer_phone' => $recipient['phone'],
                            'customer_address' => $recipient['full_address'],
                            'courier' => $order['shipping_carrier'],
                            'qty' => count($order['item_list'] ?? 0),
                            'shipping_cost' => $order['estimated_shipping_fee'],
                            'status' => $order['order_status'],
                            'buyer_username' => $order['buyer_username'],
                            'notes' => $order['message_to_seller'],
                            'payment_method' => $order['payment_method'],
                            'order_time' => date('Y-m-d H:i:s', $order['create_time'] ?? time()),
                            'total_price' => $total_price_final,
                        ]
                    );

                    $orderId = $order_proccessed->id ?? 0;

                    if (empty($orderId)) {
                        Log::channel('shopee')->info(sprintf("Failed to insert order %s", $order_sn));
                        return response()->json(['status' => sprintf("Failed to insert order %s", $order_sn)]);
                    }

                    foreach ($order['item_list'] as $item) {
                        $productId = Product::getProducts(strval($item['item_id']), strval($item['model_id'] ?? null))->value('id');
                        if (empty($productId)) {

                        } else {
                            $order_product_pre_insert = [
                                'order_id' => $orderId,
                                'product_id' => $productId,
                                'qty' => $item['model_quantity_purchased'],
                                'sale' => !empty($item['model_discounted_price']) ? $item['model_discounted_price'] : $item['model_original_price'],
                            ];

                            OrderProduct::updateOrCreate(
                                ['order_id' => $orderId, 'product_id' => $productId],
                                $order_product_pre_insert
                            );
                        }
                    }

                    DB::commit();
                break;

                case 'CANCELLED':
                    Order::where('invoice', $order_sn)
                        ->update([
                            'status' => $status,
                        ]);
                break;

                default:
                    # code...
                    break;
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('shopee')->info($e->getMessage());

            return response()->json(['status' => $e->getMessage()]);
        }
    }

    public function handleProductUpdate($data)
    {
        // {"msg_id":"306af6c3-78f1-40dd-a315-8b83f69390bb","data":{"item_id":25197402630,"model_id":0,"update_field":"original_price","old_value":95197,"new_value":100000,"update_time":1765606277},"shop_id":336094210,"code":22,"timestamp":1765606277}
        $product_online_id = strval($data['data']['item_id'] ?? null);
        $product_model_id = strval($data['data']['model_id'] ?? null);

        $product = Product::getProducts($product_online_id, $product_model_id)->first();
        if (empty($product)) {
            Log::channel('shopee')->info('Product not found for Shopee Webhook', [
                'product_online_id' => $product_online_id,
                'product_model_id' => $product_model_id
            ]);
            return response()->json(['status' => 'product not found']);
        }

        $product->sale = $data['data']['new_value'] ?? $product->sale;
        $product->save();

        Log::channel('shopee')->info('Product updated from Shopee Webhook', [
            'product_id' => $product->id,
            'product_online_id' => $product_online_id,
            'product_model_id' => $product_model_id,
            'new_sale' => $product->sale
        ]);
    }
}
