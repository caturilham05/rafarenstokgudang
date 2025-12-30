<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Jobs\SyncShopeeProductsJob;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Facades\Filament;

class ManageProducts extends ManageRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make()
            //     ->label('New Product')
            //     ->icon('heroicon-o-plus'),

            Action::make('syncProducts')
                ->label('Sinkron Produk Marketplace')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->visible(fn () => auth()->user()->hasRole('super_admin'))
                ->action(function () {
                    SyncShopeeProductsJob::dispatch()->onQueue('shopee');
                    // Filament::notify('success', 'Sinkronisasi produk dimulai (background).');
                    Notification::make()
                        ->title('Sinkronisasi produk dimulai (background service).')
                        ->success()
                        ->send();
                }),
        ];
    }
}
