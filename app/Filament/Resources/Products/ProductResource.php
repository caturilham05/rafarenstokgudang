<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Models\Product;
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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon     = Heroicon::OutlinedRectangleStack;
    protected static ?int $navigationSort                       = 1;
    protected static ?string $recordTitleAttribute              = 'Product';
    protected static string | \UnitEnum | null $navigationGroup = 'Product';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('store_id')
                    ->label('Store')
                    ->relationship('store', 'store_name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('product_online_id')
                    ->disabled(fn (string $operation) => $operation === 'edit'),
                TextInput::make('product_model_id')
                    ->disabled(fn (string $operation) => $operation === 'edit'),
                TextInput::make('product_name')
                    ->required(),
                TextInput::make('sale')
                    ->required(),
                TextInput::make('stock')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('store.store_name'),
                TextEntry::make('product_online_id'),
                TextEntry::make('product_model_id'),
                TextEntry::make('product_name'),
                TextEntry::make('url_product')
                    ->label('URL Produk')
                    ->url(fn ($state) => $state, shouldOpenInNewTab: true)
                    ->openUrlInNewTab(),
                TextEntry::make('sale')->money('IDR'),
                TextEntry::make('stock'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Product $record): bool => $record->trashed()),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),

                RepeatableEntry::make('productMasters')
                    ->label('Product Masters')
                    ->columnSpanFull()
                    ->table([
                        TableColumn::make('Product Name'),
                        TableColumn::make('Stock'),
                        TableColumn::make('Stock Conversion'),
                    ])
                    ->schema([
                        TextEntry::make('product_name'),
                        TextEntry::make('stock')->numeric(),
                        TextEntry::make('stock_conversion')->numeric(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('Product')
            ->query(
                static::getEloquentQuery()
                    ->orderBy('id', 'desc')
            )
            ->columns([
                TextColumn::make('store.marketplace_name')
                    ->label('Store')
                    ->sortable(),
                TextColumn::make('store.store_name')
                    ->label('Store')
                    ->sortable(),
                TextColumn::make('product_online_id')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('product_model_id')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('product_name'),
                TextColumn::make('varian'),
                TextColumn::make('sale')
                    ->numeric(),
                TextColumn::make('stock')
                    ->numeric()
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
                TrashedFilter::make(),
                //filter Packer
                Filter::make('product')
                    ->label('Product Name')
                    ->schema([
                        Select::make('product_name')
                            ->label('Product Name')
                            ->options(
                                Product::query()->pluck('product_name', 'product_name')
                            )
                            ->searchable()
                    ])
                    ->query(function (Builder $query, array $data) {
                        $query->when(
                            $data['product_name'] ?? null,
                            fn ($q, $value) =>
                                $q->where('product_name', $value)
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return empty($data['product_name'])
                            ? null
                            : 'Product: ' . $data['product_name'];
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
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
            'index' => ManageProducts::route('/'),
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
