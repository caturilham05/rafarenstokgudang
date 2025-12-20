<?php

namespace App\Filament\Exports;

use App\Models\OrderProduct;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;
use Illuminate\Database\Eloquent\Builder;

class OrderProductExporter extends Exporter
{
    protected static ?string $model = OrderProduct::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('order.invoice')
                ->label('Invoice'),

            ExportColumn::make('product.store.store_name')
                ->label('Store Name'),

            ExportColumn::make('product_name')
                ->label('Product'),

            ExportColumn::make('varian'),

            ExportColumn::make('qty')
                ->label('Qty'),

            ExportColumn::make('sale')
                ->label('Sale'),

            ExportColumn::make('order.order_time')
                ->label('Order Time'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your order product export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }

    // /**
    //  * Tambahan row footer (TOTAL)
    //  */
    // public static function getFooterRow(Builder $query): array
    // {
    //     $totals = $query
    //         ->clone()
    //         ->selectRaw('
    //             SUM(qty) as total_qty,
    //             SUM(sale) as total_sale
    //         ')
    //         ->first();

    //     return [
    //         'order.invoice'    => 'TOTAL',
    //         'store_name'       => '',
    //         'product_name'     => '',
    //         'varian'           => '',
    //         'qty'              => $totals->total_qty ?? 0,
    //         'sale'             => $totals->total_sale ?? 0,
    //         'order.order_time' => '',
    //     ];
    // }
}
