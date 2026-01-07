<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderProduct;
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
        DB::beginTransaction();

        try {
            $order_sn = $data['data']['ordersn'] ?? null;
            $status   = $data['data']['status'] ?? null;
            $shopId   = $data['shop_id'] ?? null;

            $store = Store::getStores($shopId)->first();
            if (is_null($store)) {
                Log::channel('shopee')->info('Toko tidak ditemukan');
                return response()->json(['status' => 'Toko tidak ditemukan']);
            }

            $accessToken = $store->access_token;
            $apiService  = app(ShopeeApiService::class);

            switch ($status) {
                case 'READY_TO_SHIP':
                    $response = $apiService->getOrderDetail($accessToken, $shopId, $order_sn);
                    if (!empty($response['error'])) {
                        throw new \Exception('Error fetch marketplace');
                    }

                    $order     = $response['response']['order_list'][0] ?? [];
                    $recipient = $order['recipient_address'] ?? [];

                    $response_escrow = $apiService->getEscrowDetail($accessToken, $shopId, $order_sn);
                    if (!empty($response_escrow['error'])) {
                        throw new \Exception('Error fetch escrow');
                    }

                    $order_income = $response_escrow['response']['order_income'] ?? [];
                    if (empty($order_income)) {
                        throw new \Exception('order income belum tersedia');
                    }

                    $commission_fee                                = $order_income['commission_fee'] ?? 0;
                    $delivery_seller_protection_fee_premium_amount = $order_income['delivery_seller_protection_fee_premium_amount'] ?? 0;
                    $service_fee                                   = $order_income['service_fee'] ?? 0;
                    $seller_order_processing_fee                   = $order_income['seller_order_processing_fee'] ?? 0;
                    $voucher_from_seller                           = $order_income['voucher_from_seller'] ?? 0;

                    $escrow_amount_after_adjustment = $order_income['escrow_amount_after_adjustment'] ?? 0;
                    $total_price                    = !empty($escrow_amount_after_adjustment) ? $escrow_amount_after_adjustment : $response_escrow['response']['buyer_payment_info']['buyer_total_amount'];

                    // $total_price_final = floor($total_price - ($total_price * 0.005));

                    $qty_total = 0;
                    foreach ($order['item_list'] as $value) {
                        $qty_total += $value['model_quantity_purchased'];
                    }

                    $orderModel = Order::updateOrCreate(
                        ['invoice' => $order_sn],
                        [
                            'invoice'                                       => $order_sn,
                            'store_id'                                      => $store->id,
                            'marketplace_name'                              => $store->marketplace_name,
                            'store_name'                                    => $store->store_name,
                            'buyer_username'                                => $order['buyer_username'],
                            'customer_name'                                 => $recipient['name'],
                            'customer_phone'                                => $recipient['phone'],
                            'customer_address'                              => $recipient['full_address'],
                            'courier'                                       => $order['package_list'][0]['shipping_carrier'],
                            'qty'                                           => $qty_total,
                            'shipping_cost'                                 => $order['estimated_shipping_fee'],
                            'status'                                        => $order['order_status'],
                            'notes'                                         => $order['message_to_seller'],
                            'payment_method'                                => $order['payment_method'],
                            'order_time'                                    => date('Y-m-d H:i:s', $order['create_time'] ?? time()),
                            'total_price'                                   => $total_price,
                            'commission_fee'                                => $commission_fee,
                            'delivery_seller_protection_fee_premium_amount' => $delivery_seller_protection_fee_premium_amount,
                            'service_fee'                                   => $service_fee,
                            'seller_order_processing_fee'                   => $seller_order_processing_fee,
                            'voucher_from_seller'                           => $voucher_from_seller

                        ]
                    );

                    // cegah double stock
                    if ($orderModel->wasRecentlyCreated === false) {
                        break;
                    }

                    foreach ($order['item_list'] as $item) {

                        $qty = (int) $item['model_quantity_purchased'];

                        $product = Product::where('product_online_id', strval($item['item_id']))
                        ->where('product_model_id', strval($item['model_id']))
                        ->lockForUpdate()
                        ->first();

                        if (!$product) {
                            continue;
                        }

                        $order_product_pre_insert = [
                            'order_id'          => $orderModel->id,
                            'product_id'        => $product->id,
                            'product_online_id' => $product->product_online_id,
                            'product_model_id'  => $product->product_model_id,
                            'product_name'      => $product->product_name,
                            'varian'            => $product->varian,
                            'qty'               => $item['model_quantity_purchased'],
                            'sale'              => !empty($item['model_discounted_price']) ? $item['model_discounted_price'] : $item['model_original_price'],
                        ];

                        OrderProduct::updateOrCreate(
                            [
                                'order_id'   => $orderModel->id,
                                'product_id' => $product->id,
                            ],
                            $order_product_pre_insert
                        );
                    }
                break;

                case 'PROCESSED':
                    $order_exists = Order::where('invoice', $order_sn)->first();
                    if (is_null($order_exists)) {
                        throw new \Exception(sprintf('invoice %s tidak terdaftar disistem', $order_sn));
                    }

                    $order_exists->update(['status' => $status]);
                break;

                case 'SHIPPED':
                    $order_exists = Order::where('invoice', $order_sn)->first();
                    if (is_null($order_exists)) {
                        throw new \Exception(sprintf('invoice %s tidak terdaftar disistem', $order_sn));
                    }

                    $order_exists->update(['status' => $status]);
                break;

                case 'TO_CONFIRM_RECEIVE':
                    $order_exists = Order::where('invoice', $order_sn)->first();
                    if (is_null($order_exists)) {
                        throw new \Exception(sprintf('invoice %s tidak terdaftar disistem', $order_sn));
                    }

                    $order_exists->update(['status' => $status]);
                break;

                case 'COMPLETED':
                    $order_exists = Order::where('invoice', $order_sn)->first();
                    if (is_null($order_exists)) {
                        throw new \Exception(sprintf('invoice %s tidak terdaftar disistem', $order_sn));
                    }

                    $store         = Store::findOrFail($order_exists->store_id);
                    $api_service   = app(ShopeeApiService::class);
                    $escrow_detail = $api_service->getEscrowDetail($store->access_token, $store->shop_id, $order_sn);

                    if (!empty($escrow_detail['error'])) {
                        throw new \Exception($escrow_detail['error']);
                    }

                    $order_income = $escrow_detail['response']['order_income'] ?? [];
                    if (empty($order_income)) {
                        throw new \Exception('order income belum tersedia');
                    }

                    $delivery_seller_protection_fee_premium_amount = $order_income['delivery_seller_protection_fee_premium_amount'] ?? 0;
                    $escrow_amount_after_adjustment                = $order_income['escrow_amount_after_adjustment'] ?? 0;
                    $total_price                                   = !empty($escrow_amount_after_adjustment) ? $escrow_amount_after_adjustment : $escrow_detail['response']['buyer_payment_info']['buyer_total_amount'];
                    $total_price_final                             = $total_price - $delivery_seller_protection_fee_premium_amount;

                    $order_exists->update([
                        'status'      => $status,
                        'total_price' => $total_price_final
                    ]);
                break;

                case 'CANCELLED':
                case 'CANCEL':
                    $order = Order::with('orderProducts')->where('invoice', $order_sn)->lockForUpdate()->first();

                    if (!$order) {
                        throw new \Exception(sprintf('invoice %s tidak terdaftar di sistem', $order_sn));
                    }

                    // kalau belum pernah scan / assign packer → cukup update status
                    if (empty($order->packer_id)) {
                        $order->update(['status' => 'CANCELLED']);
                        DB::commit();

                        return response()->json([
                            'status' => 'success',
                            'message' => 'Order dibatalkan sebelum proses scan',
                        ]);
                    }

                    // // ===============================
                    // // AMBIL SEMUA PRODUCT ID
                    // // ===============================
                    // $productIds = $order->orderProducts
                    //     ->pluck('product_id')
                    //     ->unique()
                    //     ->values();

                    // // ===============================
                    // // AMBIL SEMUA MASTER ITEM
                    // // ===============================
                    // $masterItems = ProductMasterItem::with('productMaster')
                    //     ->whereIn('product_id', $productIds)
                    //     ->lockForUpdate()
                    //     ->get()
                    //     ->groupBy('product_id');

                    // // ===============================
                    // // BALIKIN STOCK PRODUCT
                    // // ===============================
                    // foreach ($order->orderProducts as $item) {

                    //     Product::where('id', $item->product_id)
                    //         ->lockForUpdate()
                    //         ->increment('stock', $item->qty);

                    //     if (!isset($masterItems[$item->product_id])) {
                    //         throw new \Exception(sprintf(
                    //             'product [%s] tidak memiliki Product Master',
                    //             $item->product_name
                    //         ));
                    //     }

                    //     // ===============================
                    //     // BALIKIN STOCK PRODUCT MASTER
                    //     // ===============================
                    //     foreach ($masterItems[$item->product_id] as $masterItem) {

                    //         $master = $masterItem->productMaster;

                    //         $affected = ProductMaster::where('id', $master->id)
                    //             ->increment(
                    //                 'stock',
                    //                 $masterItem->stock_conversion * $item->qty
                    //             );

                    //         if ($affected === 0) {
                    //             throw new \Exception(
                    //                 "Gagal mengembalikan stock Product Master ID {$master->id}"
                    //             );
                    //         }
                    //     }
                    // }

                    // ===============================
                    // UPDATE ORDER STATUS
                    // ===============================
                    $order->update([
                        'status' => 'CANCELLED',
                    ]);
                break;

                default:
                    break;
            }

            DB::commit();
            return response()->json(['status' => 'success']);
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
        // {"msg_id":"1ed4ce6345ffa942cf653252c4118900","data":{"ordersn":"2512139K38FA5D","forder_id":"5850867173739009210","package_number":"OFG219329748205969","tracking_no":"SPXID05236113497C"},"shop_id":336094210,"code":4,"timestamp":1765814218}
        DB::beginTransaction();
        try {
            $order_sn    = $data['data']['ordersn'] ?? null;
            $tracking_no = $data['data']['tracking_no'] ?? null;

            $order_exists = Order::where('invoice', $order_sn)->first();
            if (is_null($order_exists)) {
                throw new \Exception(sprintf('invoice %s tidak terdaftar disistem', $order_sn));
            }

            $order_exists->update(['waybill' => $tracking_no]);
            DB::commit();
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('shopee')->info($e->getMessage());

            return response()->json(['status' => $e->getMessage()]);
        }
    }

    public function handleReturn($data)
    {
        try {
            $order_sn  = $data['data']['order_sn'] ?? null;
            $return_sn = $data['data']['return_sn'] ?? null;
            Log::channel('shopee')->info('order return shopee', $data);
            return response()->json(['status' => 'success']);
        } catch (\Throwable $th) {
            Log::channel('shopee')->info($th->getMessage());
            return response()->json(['status' => $th->getMessage()]);
        }
    }
}
