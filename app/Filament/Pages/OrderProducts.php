<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\OrderProduct;
use App\Models\Packer;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\DB;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Actions\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class OrderProducts extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected string $view                                       = 'filament.pages.order-products';
    protected static ?string $navigationLabel                    = 'Order Products';
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::ShoppingCart;
    protected static string | \UnitEnum | null $navigationGroup  = 'Order';
    protected static ?int $navigationSort                        = 2;

    protected function getTableQuery(): Builder
    {
        return OrderProduct::query()
            ->join('orders', 'orders.id', '=', 'order_products.order_id')
            ->leftJoin('packers', 'packers.id', '=', 'orders.packer_id')
            ->select('order_products.*');
    }

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                ExportAction::make()
                    ->label('Export Order Products')
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->askForFilename()
                            ->withColumns([
                                Column::make('order.invoice')->heading('Invoice'),
                                Column::make('order.packer.packer_name')->heading('Packer'),
                                Column::make('product.store.store_name')->heading('Store Name'),
                                Column::make('product_name')->heading('Product Name'),
                                Column::make('varian')->heading('Varian'),
                                Column::make('qty')->heading('Quantity'),
                                Column::make('sale')->heading('Sale'),
                                Column::make('order.order_time')->heading('Order time'),
                            ]),
                    ]),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('order.invoice')
                    ->label('Invoice'),

                    Tables\Columns\TextColumn::make('order.packer.packer_name')
                    ->label('Packer'),

                Tables\Columns\TextColumn::make('product.store.store_name')
                    ->label('Store Name'),

                Tables\Columns\TextColumn::make('product_name')
                    ->label('Product'),

                Tables\Columns\TextColumn::make('varian'),

                Tables\Columns\TextColumn::make('qty')
                    ->summarize(
                        Sum::make()->label('Total Qty')
                    ),

                Tables\Columns\TextColumn::make('sale')
                    ->money('IDR')
                    ->summarize(
                        Sum::make()->label('Total Sale')
                    ),

                Tables\Columns\TextColumn::make('order.order_time')
                    ->label('Order Time')
                    ->dateTime('j F Y H:i:s', 'Asia/Jakarta'),
            ])
            ->defaultSort('orders.order_time', 'desc')
            ->filters([
                //filter invoice
                Filter::make('invoice')
                    ->label('Invoice')
                    ->schema([
                        TextInput::make('invoice')
                            ->placeholder('Search Invoice...'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $query->when(
                            $data['invoice'] ?? null,
                            fn ($q, $value) =>
                                $q->where('orders.invoice', $value)
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return empty($data['invoice']) ? null : $data['invoice'];
                    }),

                //filter product name
                Filter::make('product')
                    ->label('Product')
                    ->schema([
                        Select::make('product_name')
                            ->label('Product')
                            ->searchable()
                            ->placeholder('Select product...')
                            ->getSearchResultsUsing(function (string $search) {
                                return DB::table('order_products')
                                    ->select('product_name')
                                    ->where('product_name', 'like', "%{$search}%")
                                    ->groupBy('product_name')
                                    ->orderBy('product_name')
                                    ->limit(20)
                                    ->pluck('product_name', 'product_name')
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(fn ($value) => $value),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $query->when(
                            $data['product_name'] ?? null,
                            fn ($q, $value) =>
                                $q->where('order_products.product_name', $value)
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return empty($data['product_name'])
                            ? null
                            : 'Product: ' . $data['product_name'];
                    }),

                //filter Packer
                Filter::make('packer_name')
                    ->label('Packer Name')
                    ->schema([
                        Select::make('packer_name')
                            ->label('Packer Name')
                            ->searchable()
                            ->placeholder('Select Packer Name...')
                            ->getSearchResultsUsing(function (string $search) {
                                return DB::table('packers')
                                    ->select('packer_name')
                                    ->where('packer_name', 'like', "%{$search}%")
                                    ->groupBy('packer_name')
                                    ->orderBy('packer_name')
                                    ->limit(20)
                                    ->pluck('packer_name', 'packer_name')
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(fn ($value) => $value),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $query->when(
                            $data['packer_name'] ?? null,
                            fn ($q, $value) =>
                                $q->where('packers.packer_name', $value)
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return empty($data['packer_name'])
                            ? null
                            : 'Packer: ' . $data['packer_name'];
                    }),

                Filter::make('order_time')
                    ->label('Order Time')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From')
                            ->maxDate(now()),

                        DatePicker::make('until')
                            ->label('Until')
                            ->rule(fn (callable $get) =>
                                fn (string $attribute, $value, $fail) =>
                                    $get('from') && $value < $get('from')
                                        ? $fail('End date must not be earlier than start date.')
                                        : null
                            ),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $q, $date) =>
                                    $q->whereDate('orders.order_time', '>=', $date)
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $q, $date) =>
                                    $q->whereDate('orders.order_time', '<=', $date)
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (empty($data['from']) && empty($data['until'])) {
                            return null;
                        }

                        return 'Order Time: '
                            . (date('j F Y', strtotime($data['from'])) ?? 'Any')
                            . ' → '
                            . (date('j F Y', strtotime($data['until'])) ?? 'Any');
                    }),

            ]);
    }
}
