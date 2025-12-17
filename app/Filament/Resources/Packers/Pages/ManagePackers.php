<?php

namespace App\Filament\Resources\Packers\Pages;

use App\Filament\Resources\Packers\PackerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePackers extends ManageRecords
{
    protected static string $resource = PackerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
