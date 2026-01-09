<?php

namespace App\Jobs;

use App\Models\{
    Order, Store, OrderReturn
};

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\Tiktok\TiktokApiService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\{
    InteractsWithQueue, SerializesModels
};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessTiktokOrderReturn implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 5;
    public $backoff = [30, 60, 120, 300];

    protected array $data;

    /**
     * Create a new job instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $return_id = $this->data['data']['return_id'];
            $shop_id   = $this->data['shop_id'];

            $store = Store::where('shop_id', $shop_id)->first();
            if (!$store) {
                Log::channel('tiktok')->warning('toko tidak ditemukan');
                return;
            }

            $api = new TiktokApiService($store);
            $query = [
                'shop_cipher' => $store->chiper,
            ];

            $body = [
                'return_ids' => [$return_id]
            ];

            $response = $api->post('/return_refund/202309/returns/search', $query, $body, $store->access_token);
            if (!empty($response['code'])) {
                Log::channel('tiktok')->warning($response['message']);
                $this->release(60); // retry 1 menit
                return;
            }

            $response_record = $api->get("/return_refund/202309/returns/{$return_id}/records", $query, $store->access_token);
            if (!empty($response_record['code'])) {
                Log::channel('tiktok')->warning($response_record['message']);
                $this->release(60); // retry 1 menit
                return;
            }

            $return_order         = $response['data']['return_orders'][0];
            $response_record_data = $response_record['data']['records'][0];

            DB::transaction(function () use ($return_order, $response_record_data) {

                $order = Order::where('invoice', $return_order['order_id'])->first();

                OrderReturn::updateOrCreate(
                    [
                        'invoice_return' => $return_order['return_id']
                    ],
                    [
                        'order_id'       => $order?->id,
                        'invoice_order'  => $return_order['order_id'],
                        'invoice_return' => $return_order['return_id'],
                        'waybill'        => $return_order['return_tracking_number'] ?? null,
                        'courier'        => $return_order['return_provider_name'] ?? null,
                        'reason'         => !empty($return_order['return_reason_text'])
                            ? trim($return_order['return_reason_text'])
                            : null,
                        'reason_text'   => $response_record_data['note'] ?? null,
                        'refund_amount' => $return_order['refund_amount']['refund_total'] ?? 0,
                        'return_time'   => !empty($return_order['create_time'])
                            ? date('Y-m-d H:i:s', $return_order['create_time'])
                            : null,
                        'status' => $return_order['return_status'] ?? null,
                    ]
                );
            });
        } catch (ConnectionException $e) {
            Log::channel('tiktok')->warning('TikTok API timeout', [
                'return_id' => $return_id ?? null,
                'message'   => $e->getMessage(),
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
}
