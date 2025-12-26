<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ProductMaster;
use App\Models\ProductMasterItem;
use App\Models\Store;
use App\Services\Tiktok\TiktokApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TiktokWebhookController extends Controller
{
    private function resetTiktokLogIfNeeded()
    {
        $path = storage_path('logs/tiktok.log');

        if (!file_exists($path)) {
            return false;
        }

        $maxSize  = 10 * 1024 * 1024;
        $fileSize = filesize($path);

        if ($fileSize >= $maxSize) {
            file_put_contents($path, '');
        }
    }

    public function handle(Request $request)
    {
        $data = $request->all();
        $type = $data['type'] ?? 0;
        if ($type == 0) {
            Log::channel('tiktok')->info('Failed Received Tiktok Webhook', $data);
            return response()->json(['status' => 'no code found', 'data' => $data]);
        }

        switch ($type) {
            case '1':
                $this->handleOrderDetail($data);
                break;

            default:
                # code...
                break;
        }

        Log::channel('tiktok')->info('Received Tiktok Webhook', $data);
        $this->resetTiktokLogIfNeeded();
        return response()->json(['status' => 'success']);
    }

    public function handleOrderDetail($data)
    {
        DB::beginTransaction();
        try {
            $order_id = $data['data']['order_id'];
            $status   = $data['data']['order_status'];
            $shop_id  = $data['shop_id'];

            $store = Store::where('shop_id', $shop_id)->first();
            if (is_null($store)) {
                throw new \Exception('toko tidak ditemukan');
            }
            $api = app(TiktokApiService::class);
            $query = [
                'shop_cipher' => $store->chiper,
                'ids'         => $order_id
            ];

            switch ($status) {
                case 'AWAITING_SHIPMENT':
                    $response = $api->get('/order/202309/orders', $query, $store->access_token);
                    if (!empty($response['code'])) {
                        throw new \Exception($response['message']);
                    }

                    //original_price - seller_discount
                    $order         = $response['data']['orders'][0];
                    $order_products = [];
                    foreach ($order['line_items'] as $op)
                    {
                        $total_price                   = $op['original_price'] - $op['seller_discount'];
                        $order_products[$op['sku_id']] = [
                            'product_online_id' => $op['product_id'],
                            'product_name'      => $op['product_name'],
                            'total_price'       => $total_price
                        ];
                    }

                    $package_id       = $order['packages'][0]['id'];
                    $response_package = $api->get(sprintf('/fulfillment/202309/packages/%s',$package_id), ['shop_cipher' => $store->chiper], $store->access_token);
                    if (!empty($response_package['code'])) {
                        throw new \Exception($response_package['message']);
                    }

                    $packages       = $response_package['data']['orders'][0]['skus'];
                    $quantity_total = 0;
                    foreach ($packages as &$package) {
                        $package['product_online_id'] = $order_products[$package['id']]['product_online_id'] ?? 0;
                        $package['product_model_id']  = $package['id'] ?? 0;
                        $package['product_name']      = $order_products[$package['id']]['product_name'] ?? NULL;
                        $package['sale']              = $order_products[$package['id']]['total_price'] ?? 0;
                        $package['qty']               = $package['quantity'];

                        $product = Product::where('product_online_id', $package['product_online_id'])
                        ->where('product_model_id', $package['product_model_id'])->first();

                        $package['product_id'] = $product->id ?? 0;
                        $package['varian']     = $product->varian ?? NULL;

                        $quantity_total += $package['qty'];

                        unset($package['id']);
                        unset($package['image_url']);
                        unset($package['quantity']);
                    }

                    $order_pre_insert = [
                        'store_id'         => $store->id,
                        'marketplace_name' => $store->marketplace_name,
                        'store_name'       => $store->store_name,
                        'customer_name'    => $order['recipient_address']['name'],
                        'customer_phone'   => $order['recipient_address']['phone_number'],
                        'customer_address' => $order['recipient_address']['full_address'],
                        'courier'          => $order['shipping_provider'],
                        'qty'              => $quantity_total,
                        'shipping_cost'    => $order['payment']['original_shipping_fee'],
                        'total_price'      => $order['payment']['total_amount'],
                        'status'           => $status,
                        'notes'            => $order['buyer_message'],
                        'payment_method'   => $order['payment_method_name'],
                        'order_time'       => date('Y-m-d H:i:s', $order['create_time'])
                    ];

                    $order_insert = Order::updateOrCreate(
                        [
                            'invoice' => $order['id']
                        ],
                        $order_pre_insert
                    );
                    foreach ($packages as $package_insert) {
                        OrderProduct::updateOrCreate(
                            [
                                'order_id'   => $order_insert->id,
                                'product_id' => $package_insert['product_id']
                            ],
                            $package_insert
                        );
                    }
                break;

                case 'AWAITING_COLLECTION':
                    $order_exists = Order::where('invoice', $order_id)->first();
                    if (is_null($order_exists)) {
                        throw new \Exception(sprintf('order %s tidak ditemukan', $order_id));
                    }

                    $response = $api->get('/order/202309/orders', $query, $store->access_token);
                    if (!empty($response['code'])) {
                        throw new \Exception($response['message']);
                    }

                    $waybill = $response['data']['orders'][0]['tracking_number'];

                    $order_exists->update([
                        'status'  => $status,
                        'waybill' => $waybill
                    ]);
                break;

                case 'IN_TRANSIT':
                    $order_exists = Order::where('invoice', $order_id)->first();
                    if (is_null($order_exists)) {
                        throw new \Exception(sprintf('order %s tidak ditemukan', $order_id));
                    }

                    $order_exists->update([
                        'status'  => $status,
                    ]);
                break;

                case 'DELIVERED':
                    $order_exists = Order::where('invoice', $order_id)->first();
                    if (is_null($order_exists)) {
                        throw new \Exception(sprintf('order %s tidak ditemukan', $order_id));
                    }

                    $order_exists->update([
                        'status'  => $status,
                    ]);
                break;

                case 'COMPLETED':
                    $order_exists = Order::where('invoice', $order_id)->first();
                    if (is_null($order_exists)) {
                        throw new \Exception(sprintf('order %s tidak ditemukan', $order_id));
                    }

                    $order_exists->update([
                        'status'  => $status,
                    ]);
                break;

                case 'CANCEL':
                    $order = Order::where('invoice', $order_id)->lockForUpdate()->first();
                    if (is_null($order)) {
                        throw new \Exception(sprintf('order %s tidak ditemukan', $order_id));
                    }

                    // kalau belum pernah scan / assign packer → cukup update status
                    if (empty($order->packer_id)) {
                        $order->update(['status' => $status]);
                        DB::commit();

                        return response()->json([
                            'status' => 'success',
                            'message' => 'Order dibatalkan sebelum proses scan',
                        ]);
                    }

                    // ===============================
                    // AMBIL SEMUA PRODUCT ID
                    // ===============================
                    $productIds = $order->orderProducts
                        ->pluck('product_id')
                        ->unique()
                        ->values();

                    // ===============================
                    // AMBIL SEMUA MASTER ITEM
                    // ===============================
                    $masterItems = ProductMasterItem::with('productMaster')
                        ->whereIn('product_id', $productIds)
                        ->lockForUpdate()
                        ->get()
                        ->groupBy('product_id');

                    // ===============================
                    // BALIKIN STOCK PRODUCT
                    // ===============================
                    foreach ($order->orderProducts as $item) {

                        Product::where('id', $item->product_id)
                            ->lockForUpdate()
                            ->increment('stock', $item->qty);

                        if (!isset($masterItems[$item->product_id])) {
                            throw new \Exception(sprintf(
                                'product [%s] tidak memiliki Product Master',
                                $item->product_name
                            ));
                        }

                        // ===============================
                        // BALIKIN STOCK PRODUCT MASTER
                        // ===============================
                        foreach ($masterItems[$item->product_id] as $masterItem) {

                            $master = $masterItem->productMaster;

                            $affected = ProductMaster::where('id', $master->id)
                                ->increment(
                                    'stock',
                                    $master->stock_conversion * $item->qty
                                );

                            if ($affected === 0) {
                                throw new \Exception(
                                    "Gagal mengembalikan stock Product Master ID {$master->id}"
                                );
                            }
                        }
                    }

                    // ===============================
                    // UPDATE ORDER STATUS
                    // ===============================
                    $order->update([
                        'status' => $status,
                    ]);
                break;

                default:
                    # code...
                    break;
            }
            DB::commit();
            return response()->json(['status' => 'success']);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::channel('tiktok')->info($th->getMessage());
            return response()->json(['status' => $th->getMessage()]);
        }
    }
}
