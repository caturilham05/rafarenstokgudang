<?php

namespace App\Console\Commands;

use App\Jobs\ProcessTiktokOrderWebhook;
use App\Models\Order;
use App\Models\Store;
use App\Services\Tiktok\TiktokApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TiktokSyncMissingOrder extends Command
{
    protected $signature = 'tiktok:sync-missing
                            {--store= : Store ID}
                            {--from= : YYYY-MM-DD}
                            {--to= : YYYY-MM-DD}';

    protected $description = 'Sync TikTok order yang belum masuk ke sistem';

    public function handle()
    {
        set_time_limit(0);

        $storeId = $this->option('store');
        $from    = $this->option('from');
        $to      = $this->option('to');

        if (!$from || !$to) {
            $this->error('Parameter --from dan --to wajib diisi');
            return self::FAILURE;
        }

        $timeFrom = strtotime($from . ' 00:00:00');
        $timeTo   = strtotime($to . ' 23:59:59');

        $stores = Store::when($storeId, fn ($q) => $q->where('id', $storeId))->get();

        if ($stores->isEmpty()) {
            $this->warn('Store tidak ditemukan');
            return self::FAILURE;
        }

        $limit   = 100;
        $maxPage = 50;
        $delayUs = 300000;

        foreach ($stores as $store) {

            if ($store->marketplace_name !== 'Tiktok') {
                continue;
            }

            $this->info("▶ Sync TikTok Store ID {$store->id}");
            Log::channel('tiktok')->info('Start sync TikTok order', [
                'store_id' => $store->id,
                'from'     => $from,
                'to'       => $to,
            ]);

            $api  = new TiktokApiService($store);
            $page = 1;

            while ($page <= $maxPage) {

                $this->line("  - Page {$page}");

                $response = $api->post(
                    '/order/202309/orders/search',
                    [
                        'shop_cipher' => $store->chiper,
                        'page'        => $page,
                        'page_size'   => $limit,
                    ],
                    [
                        'create_time_ge' => $timeFrom,
                        'create_time_lt' => $timeTo,
                        'order_status'   => 'AWAITING_SHIPMENT'
                    ],
                    $store->access_token
                );

                if (!empty($response['code'])) {
                    $this->warn($response['message']);
                    Log::channel('tiktok')->warning($response['message']);
                    break;
                }

                $orders = $response['data']['orders'] ?? [];

                if (empty($orders)) {
                    break;
                }

                foreach ($orders as $order) {

                    $orderId = $order['id'] ?? null;
                    $status  = $order['status'] ?? null;

                    if (!$orderId || !$status) {
                        continue;
                    }

                    if (Order::where('invoice', $orderId)->exists()) {
                        continue;
                    }

                    $payload = [
                        'shop_id' => $store->shop_id,
                        'data' => [
                            'order_id'     => $orderId,
                            'order_status' => $status,
                        ],
                    ];

                    dispatch(new ProcessTiktokOrderWebhook($payload))
                        ->onQueue('tiktok');

                    usleep($delayUs);
                }

                if (count($orders) < $limit) {
                    break;
                }

                $page++;
            }

            Log::channel('tiktok')->info('Finish sync TikTok order', [
                'store_id' => $store->id,
                'last_page' => $page,
            ]);

            $this->info("✔ Store {$store->id} selesai");
        }

        $this->info('🎉 Sync TikTok selesai');
        return self::SUCCESS;
    }
}
