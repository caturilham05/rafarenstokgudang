<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopeeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Tangani webhook dari Shopee di sini
        $data = $request->all();

        // Contoh: Log data webhook
        Log::info('Received Shopee Webhook:', $data);

        // Lakukan proses sesuai kebutuhan, misalnya memperbarui status pesanan, inventaris, dll.

        return response()->json(['status' => 'success']);
    }

}
