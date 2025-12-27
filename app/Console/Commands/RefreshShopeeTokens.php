<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\ShopeeController;
use App\Http\Controllers\TiktokController;
use App\Models\Store;

class RefreshShopeeTokens extends Command
{
    protected $signature   = 'shopee:refresh-tokens';
    protected $description = 'Refresh all Shopee store tokens via ShopeeController';

    public function handle()
    {
        $logFile = storage_path('logs/shopee-refresh.log');
        if (file_exists($logFile)) {
            $lines = count(file($logFile));  // hitung baris
            if ($lines >= 100) {
                file_put_contents($logFile, '');  // hapus isi file
            }
        }
        $authService      = app(\App\Services\Shopee\ShopeeAuthService::class);
        $controller       = app(ShopeeController::class);
        $controllerTiktok = app(TiktokController::class);
        $stores           = Store::getStores();

        foreach ($stores as $store)
        {
            try {
                switch ($store->marketplace_name) {
                    case 'Shopee':
                    case 'shopee':
                        $result = $controller->refreshToken($store->shop_id, $store->refresh_token, $authService);
                    break;

                    case 'Tiktok':
                    case 'tiktok':
                        if (!empty($store->shop_id)) {
                            $result = $controllerTiktok->refreshToken($store->shop_id);
                        }
                        // $result = $controllerTiktok->refreshToken($store->shop_id, $store->refresh_token, $authServiceTiktok);
                    break;

                    default:
                        # code...
                        break;
                }

                $resultEncoded = json_encode($result ?? NULL);
                $this->info("Refreshing token for Store Name: {$store->store_name}, Shop ID: {$store->shop_id}");
                $message = "[".now()."] Store {$store->store_name} (Shop Id: {$store->shop_id}): {$resultEncoded}";
                file_put_contents($logFile, $message . PHP_EOL, FILE_APPEND);
            } catch (\Exception $e) {
                $error = "[".now()."] Store {$store->store_name} (Shop Id: {$store->shop_id}) ERROR: ".$e->getMessage();
                file_put_contents($logFile, $error . PHP_EOL, FILE_APPEND);
            }
        }
    }
}
