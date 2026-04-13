<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Order;
use Livewire\Attributes\On;

class OrderOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;
    public $date;
    public $status;

    public function mount()
    {
        $this->date   = now()->toDateString();
        $this->status = ['cancel', 'cancelled'];
    }

    #[On('dateFilterChanged')]
    public function updateDate($date)
    {
        $this->date = $date;
    }


    protected function getStats(): array
    {
        return [
            Stat::make('Shopee Order',
                Order::whereDate('scanned_at', $this->date)
                ->whereNotIn('status', $this->status)
                ->where('marketplace_name', 'shopee')
                // ->where('is_printed', 1)
                ->count()
            ),

            Stat::make('Tiktok Order',
                Order::whereDate('scanned_at', $this->date)
                ->whereNotIn('status', $this->status)
                ->where('marketplace_name', 'tiktok')
                // ->where('is_printed', 1)
                ->count()
            ),

            Stat::make('Shopee Cancel',
                Order::whereIn('status', $this->status)
                 ->whereDate('scanned_at', $this->date)
                ->where('marketplace_name', 'shopee')
                ->count()
            ),

            Stat::make('Tiktok Cancel',
                Order::whereIn('status', $this->status)
                 ->whereDate('scanned_at', $this->date)
                ->where('marketplace_name', 'tiktok')
                ->count()
            ),
        ];
    }
}
