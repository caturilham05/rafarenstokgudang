<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\Store;
use App\Services\Shopee\ShopeeApiService;
use App\Services\Tiktok\TiktokApiService;

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
        $service   = app(ShopeeApiService::class);
        $stores    = Store::getStores();
        // $apiTiktok = app(TiktokApiService::class);

        foreach ($stores as $store)
        {
            $this->info("Syncing products for Store: {$store->store_name} ({$store->shop_id})");

            $offset    = 0;
            $pageSize  = 10;
            $maxPage   = 100;
            $pageCount = 0;

            switch ($store->marketplace_name) {
                case 'shopee':
                case 'Shopee':
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

                                    $stock = $model['stock_info_v2']['summary_info']['total_available_stock'] ?? 0;
                                    $sale  = $model['price_info'][0]['current_price'] ?? 0;

                                    Product::updateOrCreate(
                                        [
                                            'product_online_id' => $product_online_id,
                                            'product_model_id'  => $product_model_id,
                                            'store_id'          => $store->id,
                                        ],
                                        [
                                            'product_name' => $product_name,
                                            'varian'       => $model_name,
                                            'url_product'  => 'https://shopee.co.id/product/'.$store->shop_id.'/'.$product_online_id,
                                            'sale'         => $sale,
                                            'stock'        => $stock,
                                        ]
                                    );
                                }
                            } else {
                                $sale  = $product['price_info'][0]['current_price'] ?? 0;
                                $stock = $product['stock_info_v2']['summary_info']['total_available_stock'] ?? 0;

                                Product::updateOrCreate(
                                    [
                                        'product_online_id' => $product_online_id,
                                        'store_id'          => $store->id,
                                    ],
                                    [
                                        'product_name' => $product_name,
                                        'url_product'  => 'https://shopee.co.id/product/'.$store->shop_id.'/'.$product_online_id,
                                        'sale'         => $sale,
                                        'stock'        => $stock,
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
                break;

                case 'tiktok':
                case 'Tiktok':
                    $apiTiktok = new TiktokApiService($store);
                    try {
                        $path      = "/product/202502/products/search";
                        $pageToken = '';

                        do {
                            $query = [
                                'shop_cipher' => $store->chiper,
                                'version'     => '202502',
                                'page_size'   => $pageSize,
                                'page_token'  => $pageToken,
                            ];

                            $body = [
                                'status' => 'ACTIVATE',
                            ];

                            $response = $apiTiktok->post(
                                $path,
                                $query,
                                $body,
                                $store->access_token
                            );

                            if (!empty($response['code'])) {
                                throw new \Exception($response['message'] ?? 'Tiktok API error');
                            }

                            $products = $response['data']['products'] ?? [];

                            if (empty($products)) {
                                $this->info("No more TikTok products for store {$store->store_name}");
                                break;
                            }

                            foreach ($products as $item) {
                                $product_online_id = (string) $item['id'];
                                $product_name      = $item['title'] ?? '-';
                                $skus              = $item['skus'] ?? [];

                                if (!empty($skus)) {
                                    foreach ($skus as $sku) {
                                        Product::updateOrCreate(
                                            [
                                                'product_online_id' => $product_online_id,
                                                'product_model_id'  => (string) ($sku['id'] ?? 0),
                                                'store_id'          => $store->id,
                                            ],
                                            [
                                                'product_name' => $product_name,
                                                'varian'       => $sku['seller_sku'] ?? NULL,
                                                'sale'         => $sku['price']['tax_exclusive_price'],
                                                'stock'        => $sku['inventory'][0]['quantity']
                                            ]
                                        );
                                    }
                                }
                            }

                            $this->info("Fetched " . count($products) . " TikTok products");

                            $pageToken = $response['data']['next_page_token'] ?? null;

                            sleep(1);

                        } while (!empty($pageToken));

                    } catch (\Throwable $th) {
                        $this->error(
                            "Syncing products for Store Tiktok Failed: {$th->getMessage()} ({$store->store_name})"
                        );
                    }
                break;

                default:
                    # code...
                    break;
            }
        }
    }
}
