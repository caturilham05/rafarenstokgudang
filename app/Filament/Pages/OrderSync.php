<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class OrderSync extends Page
{
    protected string $view = 'filament.pages.order-sync';
    protected static ?string $title = 'Sinkron Order';
    protected static ?string $navigationLabel = 'Sinkron Order';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-path';
    protected static string | \UnitEnum | null $navigationGroup = 'Order';
    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('Update:Order') ?? false;
    }
}
