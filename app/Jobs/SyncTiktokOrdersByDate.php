<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Store;
use App\Services\Tiktok\TiktokApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncTiktokOrdersByDate implements ShouldQueue
{
    use Queueable;

    public $tries = 5;
    public $backoff = [30, 60, 120, 300];

    public function __construct(public int $storeId, public string $date)
    {
        $this->onQueue('tiktok');
    }

    public function handle(): void
    {
        $store = Store::findOrFail($this->storeId);
        $timeFrom = strtotime($this->date . ' 00:00:00');
        $timeTo = strtotime($this->date . ' 23:59:59');
        $api = new TiktokApiService($store);

        $pageToken = null;
        for ($page = 0; $page < 500; $page++) {
            $response = $api->post(
                '/order/202309/orders/search',
                array_filter([
                    'shop_cipher' => $store->chiper,
                    'page_size' => 100,
                    'page_token' => $pageToken,
                ], fn ($value) => $value !== null),
                ['create_time_ge' => $timeFrom, 'create_time_lt' => $timeTo],
                $store->access_token
            );

            if (!empty($response['code'])) {
                throw new \RuntimeException($response['message']);
            }

            $orders = $response['data']['orders'] ?? [];
            if (!$orders) {
                break;
            }

            foreach ($orders as $order) {
                $orderId = $order['id'] ?? null;
                $status = $order['status'] ?? null;
                if (!$orderId || !$status || Order::where('invoice', $orderId)->exists()) {
                    continue;
                }

                ProcessTiktokOrderWebhook::dispatch([
                    'shop_id' => $store->shop_id,
                    'data' => ['order_id' => $orderId, 'order_status' => $status],
                ])->onConnection($this->connection)->onQueue('tiktok');
            }

            $nextPageToken = $response['data']['next_page_token'] ?? null;
            if (!$nextPageToken) {
                break;
            }
            $pageToken = $nextPageToken;
        }
    }
}
