<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Store;
use App\Services\Shopee\ShopeeApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncShopeeOrdersByDate implements ShouldQueue
{
    use Queueable;

    public $tries = 5;
    public $backoff = [30, 60, 120, 300];

    public function __construct(public int $storeId, public string $date)
    {
        $this->onQueue('shopee');
    }

    public function handle(ShopeeApiService $api): void
    {
        $store = Store::findOrFail($this->storeId);
        $start = strtotime($this->date . ' 00:00:00');
        $end = strtotime($this->date . ' 23:59:59');
        $cursor = '';

        for ($page = 0; $page < 500; $page++) {
            $response = $api->getOrder($store->access_token, $store->shop_id, (string) $start, (string) $end, 100, null, 'create_time', $cursor);
            if (!empty($response['error'])) {
                throw new \RuntimeException($response['message'] ?? $response['error']);
            }

            $orders = $response['response']['order_list'] ?? [];
            if (!$orders) {
                break;
            }

            foreach ($orders as $order) {
                $orderSn = $order['order_sn'] ?? null;
                if (!$orderSn || Order::where('invoice', $orderSn)->exists()) {
                    continue;
                }

                ProcessShopeeOrder::dispatch($store->id, $orderSn)->onConnection($this->connection)->onQueue('shopee');
            }

            $nextCursor = $response['response']['next_cursor'] ?? '';
            if (!$nextCursor || $nextCursor === $cursor) {
                break;
            }
            $cursor = $nextCursor;
        }
    }
}
