<?php

namespace App\Filament\Resources\Boxes;

use App\Filament\Resources\Boxes\Pages\CreateBox;
use App\Filament\Resources\Boxes\Pages\EditBox;
use App\Filament\Resources\Boxes\Pages\ListBoxes;
use App\Filament\Resources\Boxes\Pages\ViewBox;
use App\Filament\Resources\Boxes\Schemas\BoxForm;
use App\Filament\Resources\Boxes\Schemas\BoxInfolist;
use App\Filament\Resources\Boxes\Tables\BoxesTable;
use App\Models\Box;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BoxResource extends Resource
{
    protected static ?string $model = Box::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | \UnitEnum | null $navigationGroup = 'Box';
    protected static ?string $recordTitleAttribute              = 'Box';
    protected static ?int $navigationSort                       = 1;      // Urutan menu


    public static function form(Schema $schema): Schema
    {
        return BoxForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BoxInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BoxesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBoxes::route('/'),
        ];
    }
}
