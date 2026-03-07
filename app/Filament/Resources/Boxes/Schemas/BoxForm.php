<?php

namespace App\Filament\Resources\Boxes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BoxForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->disabled(fn (string $operation) => $operation === 'edit'),
                TextInput::make('qty')
                    ->required()
                    ->numeric(),
            ]);
    }
}
