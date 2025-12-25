<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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

    }
}
