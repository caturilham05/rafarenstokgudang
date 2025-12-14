<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Models\Order;
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
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Order';

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

                TextEntry::make('store_name')
                    ->label('Store Name')
                    ->getStateUsing(fn ($record) =>
                        $record->orderProducts->first()?->product?->store?->store_name ?? '-'
                    ),

                TextEntry::make('buyer_username'),
                TextEntry::make('customer_name'),
                TextEntry::make('customer_phone'),
                TextEntry::make('customer_address'),
                TextEntry::make('courier'),
                TextEntry::make('status')->badge(),
                TextEntry::make('qty')->numeric(),
                TextEntry::make('discount')->money('IDR'),
                TextEntry::make('shipping_cost')->money('IDR'),
                TextEntry::make('total_price')->money('IDR'),
                TextEntry::make('notes'),
                TextEntry::make('payment_method'),

                TextEntry::make('order_time')
                    ->label('Order Date')
                    ->dateTime('j F Y H:i:s', 'Asia/Jakarta'),

                RepeatableEntry::make('orderProducts')
                    ->label('Order Products')
                    ->columnSpanFull()
                    ->table([
                        TableColumn::make('Product'),
                        TableColumn::make('Varian'),
                        TableColumn::make('Qty'),
                        TableColumn::make('Sale'),
                    ])
                    ->schema([
                        TextEntry::make('product.product_name'),
                        TextEntry::make('product.varian'),
                        TextEntry::make('qty'),
                        TextEntry::make('sale'),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('Order')
            ->columns([
                TextColumn::make('invoice')
                    ->searchable(),
                TextColumn::make('store_name')
                    ->label('Store Name')
                    ->getStateUsing(fn ($record) =>
                        $record->orderProducts->first()?->product?->store?->store_name ?? '-'
                    ),
                TextColumn::make('buyer_username')
                    ->searchable(),
                // TextColumn::make('customer_address')
                //     ->limit(30)
                //     ->searchable(),
                TextColumn::make('qty')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_price')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
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
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                // EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
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
