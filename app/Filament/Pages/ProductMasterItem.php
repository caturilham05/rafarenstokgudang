<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\ProductMaster as ProductMasterModel;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ProductMasterItem extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug                  = 'product-master/{record}/items';
    protected string $view                          = 'filament.pages.product-master-item';

    public int $record;
    public ProductMasterModel $ProductMasterModel;

    public function mount(int $record): void
    {
        $this->ProductMasterModel = ProductMasterModel::with([
            'items.product'
        ])->findOrFail($record);
    }

    public function getHeading(): string
    {
        return  sprintf('Product Master %s', $this->ProductMasterModel->product_name);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Product Master')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(ProductMaster::getUrl()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                \App\Models\ProductMasterItem::query()
                    ->where('product_master_id', $this->record)
                    ->with('product')
            )
            ->columns([
                Tables\Columns\TextColumn::make('product.store.store_name')
                ->label('Store'),

                Tables\Columns\TextColumn::make('product.product_name')
                    ->label('Product Marketplace'),

                // Tables\Columns\TextColumn::make('product.url_product')
                //     ->label('URL Product Marketplace'),

                Tables\Columns\TextColumn::make('product.url_product')
                    ->label('URL Product Marketplace')
                    ->url(fn ($record) => $record->product?->url_product)
                    ->openUrlInNewTab()
                    ->limit(30),

                Tables\Columns\TextColumn::make('product.varian')
                    ->label('Product Varian'),

                Tables\Columns\TextColumn::make('product.sale')
                    ->label('Sale'),

                Tables\Columns\TextColumn::make('product.stock')
                    ->label('Stock Marketplace'),
            ]);
    }
}
