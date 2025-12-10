<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Actions\Action;

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
                    // Taruh logika sinkron di sini
                    // Contoh: dispatch job
                    // \App\Jobs\SyncMarketplaceProducts::dispatch();

                    // Filament::notify('success', 'Sinkron produk sedang diproses!');
                }),
        ];
    }
}
