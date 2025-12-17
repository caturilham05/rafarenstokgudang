<?php

namespace App\Filament\Resources\Stores;

use App\Filament\Resources\Stores\Pages\ManageStores;
use App\Models\Store;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Store';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('marketplace_name'),
                TextInput::make('store_name'),
                TextInput::make('store_url')
                    ->url(),
                TextInput::make('marketplace_id')
                    ->numeric(),
                TextInput::make('shop_id')
                    ->numeric(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('marketplace_name')
                    ->placeholder('-'),
                TextEntry::make('store_name')
                    ->placeholder('-'),
                TextEntry::make('store_url')
                    ->placeholder('-'),
                TextEntry::make('marketplace_id'),
                TextEntry::make('shop_id'),
                TextEntry::make('token_expires_at')
                    ->dateTime('j F Y H:i:s', 'Asia/Jakarta')
                    ->placeholder('-'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('Store')
            ->columns([
                TextColumn::make('marketplace_name')
                    ->searchable(),
                TextColumn::make('store_name')
                    ->searchable(),
                TextColumn::make('store_url')
                    ->searchable(),
                TextColumn::make('marketplace_id')
                    // ->numeric()
                    ->sortable(),
                TextColumn::make('shop_id')
                    // ->numeric()
                    ->sortable(),
                TextColumn::make('token_expires_at')
                    ->dateTime()
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
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStores::route('/'),
        ];
    }
}
