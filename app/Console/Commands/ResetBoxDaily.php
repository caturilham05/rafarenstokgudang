<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Box;

class ResetBoxDaily extends Command
{
    protected $signature = 'boxes:reset-daily';
    protected $description = 'Reset qty_in dan qty_out setiap hari';

    public function handle()
    {
        Box::query()->update([
            'qty_in' => 0,
            'qty_out' => 0,
        ]);

        $this->info('Qty_in dan Qty_out berhasil di reset');
    }
}
