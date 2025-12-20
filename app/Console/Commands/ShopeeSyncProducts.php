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
        $stores  = Store::getStores();

        foreach ($stores as $store) {
            $this->info("Syncing products for Store: {$store->store_name} ({$store->shop_id})");

            $offset    = 0;
            $pageSize  = 10;
            $maxPage   = 100;
            $pageCount = 0;

            while ($pageCount < $maxPage) {
                $this->info("Fetching offset {$offset}");

                $response = $service->getProducts(
                    $store->access_token,
                    $store->shop_id,
                    $offset,
                    $pageSize
                );

                if (!empty($response['error'])) {
                    $this->error("Shopee API error: " . json_encode($response));
                    break;
                }

                $items = $response['response']['item'] ?? [];

                if (empty($items)) {
                    $this->info("No more products. Stop syncing store {$store->store_name}");
                    break;
                }

                foreach ($items as $item) {
                    $product_online_id = (string) $item['item_id'];
                    $product           = $item['product'] ?? [];
                    $product_name      = $product['item_name'] ?? '-';
                    $models            = $item['models'] ?? [];

                    if (!empty($models)) {
                        foreach ($models as $model) {
                            $product_model_id = (string) $model['model_id'];
                            $model_name       = $model['model_name'] ?? '';

                            Product::updateOrCreate(
                                [
                                    'product_online_id' => $product_online_id,
                                    'product_model_id'  => $product_model_id,
                                    'store_id'          => $store->id,
                                ],
                                [
                                    'product_name' => $product_name,
                                    'varian'       => $model_name,
                                    'url_product'  => 'https://shopee.co.id/product/'.$store->shop_id.'/'.$product_online_id
                                ]
                            );
                        }
                    } else {
                        Product::updateOrCreate(
                            [
                                'product_online_id' => $product_online_id,
                                'store_id'          => $store->id,
                            ],
                            [
                                'product_name' => $product_name,
                                'url_product'  => 'https://shopee.co.id/product/'.$store->shop_id.'/'.$product_online_id
                            ]
                        );
                    }
                }

                $this->info("Fetched " . count($items) . " items");

                $offset += $pageSize;
                $pageCount++;

                sleep(1);
            }

            sleep(2);
        }
    }
}
