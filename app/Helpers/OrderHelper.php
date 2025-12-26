<?php

namespace App\Helpers;

use App\Models\Order;

class OrderHelper
{
    public static function orderStatus(): array
    {
        $statuses = Order::query()
            ->whereNotNull('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status', 'status')
            ->toArray();

        return $statuses;
    }
}
