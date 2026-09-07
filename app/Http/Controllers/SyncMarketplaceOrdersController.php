<?php

namespace App\Http\Controllers;

use App\Jobs\SyncMarketplaceOrders;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SyncMarketplaceOrdersController extends Controller
{
    public function __invoke(Request $request)
    {
        if (!$request->user()) {
            return redirect('/login');
        }
        abort_unless($request->user()->can('Update:Order'), 403);
        $input = $request->validate([
            'date_start'  => ['required', 'date_format:Y-m-d'],
            'date_end'    => ['required', 'date_format:Y-m-d', 'after_or_equal:date_start'],
            'marketplace' => ['sometimes', 'in:all,tiktok,shopee'],
        ]);
        $connection = config('queue.default');
        abort_unless(in_array(config("queue.connections.{$connection}.driver"), ['redis', 'database', 'sqs', 'beanstalkd']), 503, 'Gunakan koneksi queue asynchronous.');

        $start = Carbon::parse($input['date_start'])->startOfDay()->timestamp;
        $end   = Carbon::parse($input['date_end'])->addDay()->startOfDay()->timestamp;
        $count = 0;
        foreach (Store::whereIn('marketplace_name', ['Tiktok', 'Shopee'])->get() as $store) {
            $marketplace = strtolower($store->marketplace_name);
            if (($input['marketplace'] ?? 'all') !== 'all' && $input['marketplace'] !== $marketplace) {
                continue;
            }
            SyncMarketplaceOrders::dispatch($store->id, $marketplace, $start, $end)->onConnection($connection);
            $count++;
        }

        return response()->json(['status' => 'queued', 'stores' => $count, 'timezone' => config('app.timezone')] + $input, 202);
    }
}
