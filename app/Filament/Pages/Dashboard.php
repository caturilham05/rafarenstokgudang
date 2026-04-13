<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;

class Dashboard extends Page
{
    protected string $view = 'filament.pages.dashboard';
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::Home;

    public $date;

    public function mount()
    {
        $this->date = now()->toDateString(); // default hari ini
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('filterDate')
                ->label('Filter Tanggal')
                ->form([
                    DatePicker::make('date')
                        ->default($this->date)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->date = $data['date']; // ✅ langsung set state
                    $this->dispatch('dateFilterChanged', date: $this->date);
                }),
        ];
    }
}
