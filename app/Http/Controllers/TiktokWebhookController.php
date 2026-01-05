<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTiktokOrderWebhook;
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
            return response()->json(['status' => 'ignored']);
        }

        if ((string) $type === '1') {
            ProcessTiktokOrderWebhook::dispatch($data)->onQueue('tiktok');
        }

        Log::channel('tiktok')->info('Received Tiktok Webhook', $data);
        $this->resetTiktokLogIfNeeded();
        return response()->json(['status' => 'success']);
    }
}
