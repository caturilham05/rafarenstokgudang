<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\Store;
use App\Services\Shopee\ShopeeApiService;

class ShopeeSyncProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopee:sync-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync products from Shopee stores';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $service = app(ShopeeApiService::class);
        $stores = Store::getStores();
        foreach ($stores as $store) {
            $this->info("Syncing products for Store Name: {$store->store_name}, Shop ID: {$store->shop_id}");

            $offset = 0;
            $pageSize = 1;

            while (true) {
                $response = $service->getProducts($store->access_token, $store->shop_id, $offset, $pageSize);

                if (!empty($response['error'])) {
                    $this->error("Error fetching products: " . json_encode($response));
                    break;
                }

                $items = $response['response']['item'] ?? [];

                // Kalau data habis, break
                if (empty($items)  && $offset != 0) {
                    $this->info("All products synced.");
                    break;
                }

                // Kalau tidak ada data, break
                if (empty($items)) {
                    $this->info("No products to sync.");
                    break;
                }

                foreach ($items as $item)
                {
                    $product_online_id = $item['item_id'];
                    $product = $item['product'];
                    $product_name = $product['item_name'];
                    $models = $item['models'] ?? [];

                    // Produk yang ada model nya / varian
                    if (!empty($models))
                    {
                        foreach ($models as $model)
                        {
                            $product_model_id = $model['model_id'];
                            $stock = $model['stock_info_v2']['summary_info']['total_available_stock'] ?? 0;
                            $sale = $model['price_info'][0]['current_price'] ?? 0;
                            Product::updateOrCreate(
                                ['product_online_id' => $product_online_id, 'product_model_id' => $product_model_id, 'store_id' => $store->id],
                                [
                                    'product_name' => $product_name,
                                    'sale' => $sale,
                                    'stock' => $stock,
                                    'store_id' => $store->id,
                                ]
                            );
                        }
                    } else {
                        $product = $item['product'];
                        $sale = $product['price_info'][0]['current_price'] ?? 0;
                        $stock = $product['stock_info_v2']['summary_info']['total_available_stock'] ?? 0;
                        Product::updateOrCreate(
                            ['product_online_id' => $product_online_id, 'store_id' => $store->id],
                            [
                                'product_name' => $product_name,
                                'sale' => $sale,
                                'stock' => $stock,
                                'store_id' => $store->id,
                            ]
                        );
                    }

                    $product_names[] = $product_name;
                }

                $this->info("  Fetched " . count($items) . " products. {$offset}");

                // Update offset
                $offset += $pageSize;
            }
        }

        // $this->info('Stores:');
        // $this->info($stores->toJson(JSON_PRETTY_PRINT));
        // return Command::SUCCESS;
    }
}
