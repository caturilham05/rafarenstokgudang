<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Models\Order;
use App\Models\Packer;
use App\Models\Store;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\DB;
use pxlrbt\FilamentExcel\Actions\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ShoppingCart;

    protected static string | \UnitEnum | null $navigationGroup = 'Order';
    protected static ?string $recordTitleAttribute              = 'Order';
    protected static ?int $navigationSort                       = 1;        // Urutan menu

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('invoice')
                    ->required(),
                TextInput::make('store_id')
                    ->required()
                    ->numeric(),
                TextInput::make('customer_name')
                    ->required(),
                TextInput::make('customer_phone')
                    ->tel()
                    ->required(),
                TextInput::make('customer_address')
                    ->required(),
                TextInput::make('courier')
                    ->required(),
                TextInput::make('qty')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('discount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('shipping_cost')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                TextInput::make('total_price')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextEntry::make('invoice'),
                TextEntry::make('waybill'),

                TextEntry::make('total_price')
                ->money('IDR')
                ->badge()
                ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),

                TextEntry::make('marketplace_fee')
                    ->label('Marketplace Fee')
                    ->badge()
                    ->money('IDR', true)
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->helperText('Click Marketplace Fee Details below the Order Products for more information.'),

                TextEntry::make('status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'READY_TO_SHIP'      => 'warning',
                    'PROCESSED'          => 'warning',
                    'SHIPPED'            => 'warning',
                    'TO_CONFIRM_RECEIVE' => 'gray',
                    'COMPLETED'          => 'success',
                    'CANCELLED'          => 'danger',
                }),

                TextEntry::make('store_name')
                ->label('Store Name')
                ->getStateUsing(fn ($record) =>
                    $record->orderProducts->first()?->product?->store?->store_name ?? '-'
                ),

                TextEntry::make('courier'),
                TextEntry::make('buyer_username'),

                TextEntry::make('packer_name')
                ->badge()
                ->color('warning'),

                TextEntry::make('customer_name'),
                TextEntry::make('customer_phone'),
                TextEntry::make('customer_address'),
                TextEntry::make('qty')->numeric(),
                // TextEntry::make('discount')->money('IDR'),
                TextEntry::make('shipping_cost')->money('IDR'),
                TextEntry::make('notes'),
                TextEntry::make('payment_method'),

                TextEntry::make('order_time')
                    ->label('Order Date')
                    ->dateTime('j F Y H:i:s', 'Asia/Jakarta'),

                RepeatableEntry::make('orderProducts')
                    ->label('Order Products')
                    ->columnSpanFull()
                    ->table([
                        TableColumn::make('Product')->width('45%'),
                        TableColumn::make('Varian'),
                        TableColumn::make('Qty'),
                        TableColumn::make('Sale'),
                        TableColumn::make('Sale Total'),
                    ])
                    ->schema([
                        TextEntry::make('product.product_name'),
                        TextEntry::make('product.varian'),
                        TextEntry::make('qty'),
                        TextEntry::make('sale')->money('IDR'),
                        TextEntry::make('total_sale')
                            ->label('Total Sale')
                            ->money('IDR')
                            ->state(function ($record) {
                                return ($record->qty ?? 0) * ($record->sale ?? 0);
                            })
                            ->color('success'),
                    ]),

                Section::make('Marketplace Fee Detail')
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => $record->marketplace_fee > 0)
                    ->schema([
                        TextEntry::make('voucher_from_seller')
                            ->label('Voucher From Seller')
                            ->money('IDR', true),

                        TextEntry::make('commission_fee')
                            ->label('Commission Fee')
                            ->money('IDR', true),

                        TextEntry::make('delivery_seller_protection_fee_premium_amount')
                            ->label('Delivery Seller Protection')
                            ->money('IDR', true),

                        TextEntry::make('service_fee')
                            ->label('Service Fee')
                            ->money('IDR', true),

                        TextEntry::make('seller_order_processing_fee')
                            ->label('Seller Order Processing Fee')
                            ->money('IDR', true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('Order')
            ->query(
                static::getEloquentQuery()
                    ->orderBy('order_time', 'desc')
            )
            ->headerActions([
                ExportAction::make()
                    ->label('Export Orders')
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->askForFilename()
                            ->withColumns([
                                Column::make('invoice')->heading('Invoice'),
                                Column::make('waybill')->heading('Waybill'),
                                Column::make('marketplace_name')->heading('Marketplace Name'),
                                Column::make('store_name')->heading('Store Name'),
                                Column::make('buyer_username')->heading('Buyer Username'),
                                Column::make('courier')->heading('Courier'),
                                Column::make('qty')->heading('Quantity'),
                                Column::make('shipping_cost')->heading('Shipping Cost'),
                                Column::make('total_price')->heading('Total Price'),
                                Column::make('marketplace_fee')->heading('Total Marketplace Fee'),
                                Column::make('voucher_from_seller')->heading('Voucher From Seller'),
                                Column::make('seller_order_processing_fee')->heading('Seller Order Processing Fee'),
                                Column::make('service_fee')->heading('Service Fee'),
                                Column::make('delivery_seller_protection_fee_premium_amount')->heading('Protection (Premi)'),
                                Column::make('commission_fee')->heading('Commission Fee'),
                                Column::make('status')->heading('Status'),
                                Column::make('order_time')->heading('Order Time'),
                            ]),
                    ]),
            ])
            ->columns([
                TextColumn::make('invoice'),
                TextColumn::make('waybill')
                    ->label('Tracking Number'),
                TextColumn::make('store_name')
                    ->label('Store Name')
                    ->getStateUsing(fn ($record) =>
                        $record->orderProducts->first()?->product?->store?->store_name ?? '-'
                    ),
                TextColumn::make('buyer_username'),
                TextColumn::make('packer_name'),
                TextColumn::make('qty')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_price')
                    ->money('IDR')
                    ->badge()
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                TextColumn::make('marketplace_fee')
                    ->label('Marketplace Fee')
                    ->badge()
                    ->money('IDR', true)
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                TextColumn::make('status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'READY_TO_SHIP'      => 'warning',
                    'PROCESSED'          => 'warning',
                    'SHIPPED'            => 'warning',
                    'TO_CONFIRM_RECEIVE' => 'gray',
                    'COMPLETED'          => 'success',
                    'CANCELLED'          => 'danger',
                }),

                TextColumn::make('order_time')
                    ->dateTime('j F Y H:i:s', 'Asia/Jakarta')
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
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

                //filter STATUS
                Filter::make('status')
                    ->label('Status Order')
                    ->schema([
                        Select::make('status')
                            ->label('Status Order')
                            ->options(
                                [
                                    'READY_TO_SHIP'      => 'READY_TO_SHIP',
                                    'PROCESSED'          => 'PROCESSED',
                                    'SCANNING'           => 'SCANNING',
                                    'SCANNED'            => 'SCANNED',
                                    'SHIPPED'            => 'SHIPPED',
                                    'TO_CONFIRM_RECEIVE' => 'TO_CONFIRM_RECEIVE',
                                    'COMPLETED'          => 'COMPLETED',
                                    'CANCELLED'          => 'CANCELLED'
                                ]
                            )
                            ->searchable()
                    ])
                    ->query(function (Builder $query, array $data) {
                        $query->when(
                            $data['status'] ?? null,
                            fn ($q, $value) =>
                                $q->where('status', $value)
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return empty($data['status'])
                            ? null
                            : 'Status Order: ' . $data['status'];
                    }),

                //filter Store Name
                Filter::make('store_name')
                    ->label('Store Name')
                    ->schema([
                        Select::make('store_name')
                            ->label('Store Name')
                            ->options(
                                Store::query()->pluck('store_name', 'store_name')
                            )
                            ->searchable()
                    ])
                    ->query(function (Builder $query, array $data) {
                        $query->when(
                            $data['store_name'] ?? null,
                            fn ($q, $value) =>
                                $q->where('store_name', $value)
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return empty($data['store_name'])
                            ? null
                            : 'Store Name: ' . $data['store_name'];
                    }),

                //filter Packer
                Filter::make('packer')
                    ->label('Packer Name')
                    ->schema([
                        Select::make('packer_name')
                            ->label('Packer Name')
                            ->options(
                                Packer::query()->pluck('packer_name', 'packer_name')
                            )
                            ->searchable()
                    ])
                    ->query(function (Builder $query, array $data) {
                        $query->when(
                            $data['packer_name'] ?? null,
                            fn ($q, $value) =>
                                $q->where('packer_name', $value)
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
            ])
            ->recordActions([
                ViewAction::make(),
                // EditAction::make(),
                // DeleteAction::make(),
                // ForceDeleteAction::make(),
                // RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DeleteBulkAction::make(),
                    // ForceDeleteBulkAction::make(),
                    // RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOrders::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
