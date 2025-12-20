<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\ProductMaster;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ProductMasterCreate extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationLabel                    = 'Product Master Create';
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string | \UnitEnum | null $navigationGroup  = 'Product';
    protected static ?int $navigationSort                        = 3;

    protected string $view = 'filament.pages.product-master-create';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('product_id')
                ->label('Product')
                ->options(
                    Product::query()->pluck('product_name', 'id')
                )
                ->searchable()
                ->required(),

            Forms\Components\TextInput::make('product_name')
                ->required(),

            Forms\Components\TextInput::make('stock')
                ->numeric()
                ->default(0)
                ->required(),

            Forms\Components\TextInput::make('stock_conversion')
                ->numeric()
                ->default(0)
                ->helperText('stock conversion to determine how much each product sold reduces')
                ->required(),

            // Forms\Components\TextInput::make('sale')
            //     ->numeric()
            //     ->default(0),
        ];
    }

    protected function getFormStatePath(): string
    {
        return 'data';
    }

    public function create(): void
    {
        ProductMaster::create($this->data);

        Notification::make()
            ->title('Product Master berhasil ditambahkan')
            ->success()
            ->send();

        // $this->form->reset();
        $this->form->fill();
    }
}
