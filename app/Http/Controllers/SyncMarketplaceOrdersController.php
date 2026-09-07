<?php

namespace App\Http\Controllers;

use App\Jobs\SyncMarketplaceOrders;
use App\Models\Store;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

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
            'date_end'    => ['sometimes', 'date_format:Y-m-d'],
            'marketplace' => ['sometimes', 'in:all,tiktok,shopee'],
        ]);
        $input['date_end'] ??= $input['date_start'];
        if ($input['date_end'] !== $input['date_start']) {
            throw ValidationException::withMessages([
                'date_end' => 'Sinkron hanya untuk satu hari. Tanggal akhir harus sama dengan tanggal mulai.',
            ]);
        }
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

        if ($request->isMethod('post') && !$request->expectsJson()) {
            Notification::make()
                ->title($count > 0 ? 'Permintaan sinkron berhasil dikirim' : 'Tidak ada toko yang sesuai')
                ->body($count > 0
                    ? "Sinkron resi dan status untuk {$count} toko masuk antrean. Tanggal order: {$input['date_start']}. Data akan diperbarui saat proses selesai."
                    : 'Tidak ada toko untuk marketplace yang dipilih. Pilih marketplace lain atau periksa daftar toko Anda.')
                ->status($count > 0 ? 'success' : 'warning')
                ->persistent()
                ->send();

            return redirect(\App\Filament\Pages\OrderSync::getUrl())
                ->withInput($input);
        }

        return response()->json(['status' => 'queued', 'stores' => $count, 'timezone' => config('app.timezone')] + $input, 202);
    }
}
