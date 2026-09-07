<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class SyncMarketplaceOrders implements ShouldQueue
{
    use Queueable;

    public $tries = 5;
    public $backoff = [30, 60, 120, 300];

    public function __construct(
        public int $storeId,
        public string $marketplace,
        public int $start,
        public int $end,
    ) {
        $this->onQueue($marketplace);
    }

    public function handle(): void
    {
        Order::where('store_id', $this->storeId)
            ->whereNull('waybill')
            ->where('order_time', '>=', Carbon::createFromTimestamp($this->start, config('app.timezone'))->toDateTimeString())
            ->where('order_time', '<', Carbon::createFromTimestamp($this->end, config('app.timezone'))->toDateTimeString())
            ->select('id')
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    SyncOrderWaybill::dispatch($order->id)->onConnection($this->connection)->onQueue($this->marketplace);
                }
            });
    }
}
