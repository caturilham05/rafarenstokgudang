<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Order;
use Livewire\Attributes\On;

class OrderByExpedition extends Widget
{
    protected string $view = 'filament.widgets.order-by-expedition';
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 2;
    public $date;

    public function mount()
    {
        $this->date = now()->toDateString();
    }

    #[On('orderUpdated')]
    public function refreshWidget()
    {
        $this->dispatchSelf('$refresh');
    }

    #[On('dateFilterChanged')]
    public function updateDate($date)
    {
        $this->date = $date;
    }

    public function getDataProperty()
    {
        return Order::whereDate('scanned_at', $this->date)
            ->whereNotIn('status', ['cancel', 'cancelled'])
            // ->where('is_printed', 1)
            ->selectRaw('courier, COUNT(*) as total')
            ->groupBy('courier')
            ->orderByDesc('total')
            ->get();
    }
}
