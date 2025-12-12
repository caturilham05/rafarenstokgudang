<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Jobs\SyncShopeeProductsJob;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ManageProducts extends ManageRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
            Action::make('syncProducts')
                ->label('Sinkron Produk')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function () {
                    SyncShopeeProductsJob::dispatch();
                    // Filament::notify('success', 'Sinkronisasi produk dimulai (background).');
                    Notification::make()
                        ->title('Sinkronisasi produk dimulai (background service).')
                        ->success()
                        ->send();
                }),
        ];
    }
}
