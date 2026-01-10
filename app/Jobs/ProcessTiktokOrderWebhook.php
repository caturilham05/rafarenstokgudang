<?php

namespace App\Jobs;

use App\Models\{
    Order, OrderProduct, Product, Store
};
use App\Services\Tiktok\TiktokApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\{
    InteractsWithQueue, SerializesModels
};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class ProcessTiktokOrderWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // public $queue   = 'tiktok';
    public $tries   = 5;
    public $backoff = [30, 60, 120, 300];

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle()
    {

        try {
            // ===== PINDAHAN DARI CONTROLLER =====
            $order_id = $this->data['data']['order_id'];
            $status   = $this->data['data']['order_status'];
            $shop_id  = $this->data['shop_id'];

            $store = Store::where('shop_id', $shop_id)->first();
            if (!$store) {
                throw new \Exception('toko tidak ditemukan');
            }

            $api = new TiktokApiService($store);
            $query = [
                'shop_cipher' => $store->chiper,
                'ids'         => $order_id
            ];

            switch ($status) {
                case 'AWAITING_SHIPMENT':
                    $this->handleAwaitingShipment($api, $store, $query, $order_id, $status);
                    break;

                case 'AWAITING_COLLECTION':
                    $this->handleAwaitingCollection($api, $store, $query, $order_id, $status);
                    break;

                case 'IN_TRANSIT':
                case 'DELIVERED':
                case 'COMPLETED':
                    $order = $this->ensureOrderExists(
                        $api,
                        $store,
                        $query,
                        $order_id
                    );

                    // $order->update(['status' => $status]);
                    DB::transaction(function () use ($order, $status) {
                        $order->update(['status' => $status]);
                    });
                    break;

                case 'CANCEL':
                    $this->handleCancel($api, $store, $query, $order_id, $status);
                    break;
            }

        } catch (ConnectionException $e) {

            Log::channel('tiktok')->warning('TikTok API timeout', [
                'order_id' => $order_id ?? null,
                'message'  => $e->getMessage(),
            ]);
            $this->release(60); // ulangi 30 detik lagi
            return;
        } catch (\Throwable $e) {
            Log::channel('tiktok')->error($e->getMessage());

            throw $e;
        } finally {
            DB::disconnect();
        }
    }

    private function ensureOrderExists(
        TiktokApiService $api,
        Store $store,
        array $query,
        string $order_id
    ): ?Order {
        $order = Order::where('invoice', $order_id)->first();

        if ($order) {
            return $order;
        }

        Log::channel('tiktok')->warning(
            "Order {$order_id} belum ada, fetch dari TikTok"
        );

        // paksa create order
        $res = $this->handleAwaitingShipment(
            $api,
            $store,
            $query,
            $order_id,
            'AWAITING_SHIPMENT'
        );

        $order = Order::where('invoice', $order_id)->first();

        if (!$order) {
            Log::channel('tiktok')->warning(!empty($res) ? $res : 'tidak dapat buat order '.$order_id);
        }

        return $order;
    }

    private function handleAwaitingShipment($api, $store, $query, $order_id, $status)
    {
        $response = $api->get('/order/202309/orders', $query, $store->access_token);
        if (!empty($response['code'])) {
            Log::channel('tiktok')->warning($response['message']);
            $this->release(60);
            return;
        }

        $order = $response['data']['orders'][0] ?? null;
        if (!$order) {
            Log::channel('tiktok')->warning("Order {$order_id} belum tersedia di API TikTok");
            $this->release(60);
            return;
        }

        $orderProducts = [];
        foreach ($order['line_items'] as $item) {
            $orderProducts[$item['sku_id']] = [
                'product_online_id' => $item['product_id'],
                'product_name'      => $item['product_name'],
                'total_price'       => $item['original_price'] - $item['seller_discount'],
            ];
        }

        $packageId = $order['packages'][0]['id'] ?? null;
        if (!$packageId) {

            if (in_array($order['cancellation_initiator'], ['SYSTEM', 'BUYER'])) {
                Log::channel('tiktok')->warning($order['cancel_reason'] ?? '');
                return $order['cancel_reason'] ?? false;
            }

            Log::channel('tiktok')->warning("Package order {$order_id} belum tersedia, retry");
            $this->release(60);
            return;
        }

        $packageResponse = $api->get(
            sprintf('/fulfillment/202309/packages/%s', $packageId),
            ['shop_cipher' => $store->chiper],
            $store->access_token
        );

        if (!empty($packageResponse['code'])) {
            Log::channel('tiktok')->warning($packageResponse['message']);
            return;
        }

        $packages = [];
        $qtyTotal = 0;

        foreach ($packageResponse['data']['orders'][0]['skus'] as $sku) {

            $product = Product::where('product_online_id', $orderProducts[$sku['id']]['product_online_id'] ?? 0)
                ->where('product_model_id', $sku['id'])
                ->first();

            $packages[] = [
                'product_id'         => $product->id ?? 0,
                'product_online_id'  => $orderProducts[$sku['id']]['product_online_id'] ?? 0,
                'product_model_id'   => $sku['id'],
                'product_name'       => $orderProducts[$sku['id']]['product_name'] ?? null,
                'sale'               => $orderProducts[$sku['id']]['total_price'] ?? 0,
                'qty'                => $sku['quantity'],
                'varian'             => $product->varian ?? null,
            ];

            $qtyTotal += $sku['quantity'];
        }

        $orderData = [
            'store_id'         => $store->id,
            'marketplace_name' => $store->marketplace_name,
            'store_name'       => $store->store_name,
            'customer_name'    => $order['recipient_address']['name'],
            'customer_phone'   => $order['recipient_address']['phone_number'],
            'customer_address' => $order['recipient_address']['full_address'],
            'courier'          => $order['shipping_provider'],
            'qty'              => $qtyTotal,
            'shipping_cost'    => $order['payment']['original_shipping_fee'],
            'total_price'      => $order['payment']['total_amount'],
            'status'           => $status,
            'notes'            => $order['buyer_message'] ?? '',
            'payment_method'   => $order['payment_method_name'],
            'order_time'       => date('Y-m-d H:i:s', $order['create_time']),
        ];

        DB::transaction(function () use ($order, $orderData, $packages) {

            $orderModel = Order::updateOrCreate(
                ['invoice' => $order['id']],
                $orderData
            );

            foreach ($packages as $item) {
                OrderProduct::updateOrCreate(
                    [
                        'order_id'   => $orderModel->id,
                        'product_id' => $item['product_id'],
                    ],
                    $item
                );
            }
        });
    }

    private function handleAwaitingCollection($api, $store, $query, $order_id, $status)
    {
        // ===============================
        // 1. ENSURE ORDER EXISTS
        // ===============================
        $order = $this->ensureOrderExists($api, $store, $query, $order_id);
        if (!$order) {
            Log::channel('tiktok')->warning("Order {$order_id} tidak ditemukan");
            return;
        }

        // ===============================
        // 2. FETCH ORDER DETAIL (API)
        // ===============================
        $response = $api->get('/order/202309/orders', $query, $store->access_token);
        if (!empty($response['code'])) {
            Log::channel('tiktok')->warning($response['message']);
            $this->release(60);
            return;
        }

        $waybill = $response['data']['orders'][0]['tracking_number'] ?? null;

        // ===============================
        // 3. UPDATE DB (TRANSACTION)
        // ===============================
        DB::transaction(function () use ($order, $status, $waybill) {
            $order->update([
                'status'  => $status,
                'waybill' => $waybill,
            ]);
        });
    }

    private function handleCancel($api, $store, $query, $order_id, $status)
    {
        // ===============================
        // 1. ENSURE ORDER EXISTS
        // ===============================
        $order = $this->ensureOrderExists($api, $store, $query, $order_id);
        if (!$order) {
            Log::channel('tiktok')->warning("Order {$order_id} gagal diproses");
            return;
        }

        // ===============================
        // 2. SIMPLE CANCEL (NO PACKER)
        // ===============================
        if (empty($order->packer_id)) {
            DB::transaction(function () use ($order, $status) {
                $order->update(['status' => $status]);
            });
            return;
        }

        // ===============================
        // 3. ADVANCED CANCEL (STOCK LOGIC)
        // ===============================
        DB::transaction(function () use ($order, $status) {

            // NOTE:
            // Logic restore stock sengaja DI-COMMENT
            // sesuai dengan code existing kamu

            // ===============================
            // UPDATE ORDER STATUS
            // ===============================
            $order->update([
                'status' => $status,
            ]);
        });
    }
}
