<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopeeWebhookController extends Controller
{
    private function resetShopeeLogIfNeeded()
    {
        $path = storage_path('logs/shopee.log');

        if (!file_exists($path)) {
            return;
        }

        $maxSize = 10 * 1024 * 1024; // 5 MB dalam bytes
        $fileSize = filesize($path);

        if ($fileSize >= $maxSize) {
            file_put_contents($path, ''); // kosongkan file
        }
    }

    public function handle(Request $request)
    {
        // Tangani webhook dari Shopee di sini
        $data = $request->all();

        if (!empty($data) && $data['code'] !== 0) {
            switch ($data['code']) {
                case '3':
                    $this->handleOrderStatus($data);
                    break;

                default:
                    # code...
                    break;
            }
        }

        // Reset jika sudah lebih dari 5MB
        $this->resetShopeeLogIfNeeded();

        // Tulis log ke file khusus
        Log::channel('shopee')->info('Received Shopee Webhook', $data);

        // Lakukan proses sesuai kebutuhan, misalnya memperbarui status pesanan, inventaris, dll.

        return response()->json(['status' => 'success']);
    }

    public function handleOrderStatus($data)
    {
        // live {"msg_id":"85bb37f009e143af84852e17d50b572d","data":{"completed_scenario":null,"items":[],"ordersn":"2512114N2C22F2","status":"PROCESSED","update_time":1736323997},"shop_id":336094210,"code":3,"timestamp":1736323998}
        // demo {"msg_id":"85bb37f009e143af84852e17d50b572d","data":{"completed_scenario":null,"items":[],"ordersn":"2512125BFTQGKJ","status":"READY_TO_SHIP","update_time":1736323997},"shop_id":226242306,"code":3,"timestamp":1736323998}

        $demo = [
            'msg_id' => '85bb37f009e143af84852e17d50b572d',
            'data' => [
                'completed_scenario' => null,
                'items' => [],
                'ordersn' => '2512125BFTQGKJ',
                'status' => 'READY_TO_SHIP',
                'update_time' => 1736323997
            ],
            'shop_id' => 226242306,
            'code' => 3,
            'timestamp' => 1736323998
        ];


    }
}
