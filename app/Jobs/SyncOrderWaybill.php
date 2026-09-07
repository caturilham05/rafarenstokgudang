<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Store;
use App\Services\Shopee\ShopeeApiService;
use App\Services\Tiktok\TiktokApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncOrderWaybill implements ShouldQueue
{
    use Queueable;

    public $tries = 5;
    public $backoff = [30, 60, 120, 300];

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        $order = Order::whereNull('waybill')->find($this->orderId);
        if (!$order) {
            return;
        }
        $store = Store::findOrFail($order->store_id);

        switch (strtolower($store->marketplace_name)) {
            case 'shopee':
                $response = app(ShopeeApiService::class)->getTrackingNumber(
                    $store->access_token, $store->shop_id, $order->invoice,
                );

                if (!is_array($response) || !empty($response['error']) || !isset($response['response'])) {
                    throw new \RuntimeException($response['message'] ?? 'Invalid Shopee tracking response');
                }

                $waybill = $response['response']['tracking_number'] ?? null;
                $response = app(ShopeeApiService::class)->getOrderDetail(
                    $store->access_token, $store->shop_id, $order->invoice,
                );

                if (!is_array($response) || !empty($response['error']) || !isset($response['response']['order_list'])) {
                    throw new \RuntimeException($response['message'] ?? 'Invalid Shopee order response');
                }

                $detail = collect($response['response']['order_list'])->first(
                    fn ($item) => (string) ($item['order_sn'] ?? '') === (string) $order->invoice
                );

                $status = $detail['order_status'] ?? null;
                break;

            case 'tiktok':
                $response = (new TiktokApiService($store))->get('/order/202309/orders', [
                    'shop_cipher' => $store->chiper,
                    'ids' => $order->invoice,
                ], $store->access_token);

                if (!is_array($response) || !isset($response['code']) || $response['code'] != 0 || !isset($response['data']['orders'])) {
                    throw new \RuntimeException($response['message'] ?? 'Invalid TikTok order response');
                }

                $detail = collect($response['data']['orders'])->first(
                    fn ($item) => (string) ($item['id'] ?? '') === (string) $order->invoice
                );

                if (!$detail) {
                    throw new \RuntimeException('TikTok order tidak ditemukan: '.$order->invoice);
                }

                $waybill = $detail['tracking_number'] ?? null;
                $status = $detail['status'] ?? null;
                break;

            default:
                throw new \RuntimeException('Marketplace tidak didukung: '.$store->marketplace_name);
        }

        if (!is_string($status) || trim($status) === '') {
            throw new \RuntimeException('Status order tidak tersedia: '.$order->invoice);
        }

        // Recheck NULL so a newer webhook value cannot be overwritten.
        $query = Order::whereKey($order->id)->where('store_id', $store->id)
            ->where('invoice', $order->invoice)->whereNull('waybill')
            ->toBase();

        // Check the current DB status, including scans completed during the API call.
        (clone $query)->where(fn ($query) => $query
            ->where('status', '!=', 'SCANNED')->orWhereNull('status'))
            ->update(['status' => $status]);

        if (is_string($waybill) && trim($waybill) !== '') {
            $query->update(['waybill' => $waybill]);
        }
    }
}
