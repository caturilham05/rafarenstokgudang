<?php

namespace App\Filament\Resources\Boxes\Pages;

use App\Filament\Resources\Boxes\BoxResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBox extends ViewRecord
{
    protected static string $resource = BoxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // EditAction::make(),
        ];
    }
}
