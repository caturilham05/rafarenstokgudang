<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\ProductMaster;
use App\Models\ProductMasterItem;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;

class ProductMasterCreate extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms, HasPageShield;

    protected static ?string $slug                               = 'product-master/create/{record?}';
    protected static ?string $navigationLabel                    = 'Product Master Action';
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string | \UnitEnum | null $navigationGroup  = 'Product';
    protected static ?int $navigationSort                        = 3;
    protected string $view                                       = 'filament.pages.product-master-create';
    public    ?ProductMaster $record                             = null;
    public    ?array $data                                       = [];

    public function mount(?ProductMaster $record = null): void
    {
        $this->record = $record;

        if ($this->record)
        {
            $items = $this->record->items
                ->groupBy('stock_conversion')
                ->map(fn ($group) => [
                    'product_ids' => $group->pluck('product_id')->toArray(),
                    'stock_conversion' => $group->first()->stock_conversion,
                ])
                ->values()
                ->toArray();

            $this->form->fill([
                ...$this->record->toArray(),
                'items' => $items,
            ]);
        } else {
            $this->form->fill();
        }
    }

    public function getHeading(): string
    {
        return $this->record
            ? 'Edit Product Master'
            : 'Create Product Master';
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('product_name')
                ->required(),

            Forms\Components\TextInput::make('stock')
                ->numeric()
                ->default(0)
                ->required(),

            Forms\Components\Repeater::make('items')
                ->label('Product Marketplace')
                ->schema([
                    Forms\Components\Select::make('product_ids')
                        ->label('Product')
                        ->options(
                            Product::query()->pluck('product_name', 'id')
                        )
                        ->searchable()
                        ->multiple()
                        ->required(),

                    Forms\Components\TextInput::make('stock_conversion')
                        ->numeric()
                        ->default(1)
                        ->required()
                        ->helperText('How much master stock is reduced per product sold'),
                ])
                ->columns(2)
                ->minItems(1)
                ->required(),
        ];
    }

    protected function getFormStatePath(): string
    {
        return 'data';
    }

    public function create(): void
    {
        DB::beginTransaction();
        try {
            $productMaster = ProductMaster::updateOrCreate(
                ['id' => $this->record?->id],
                Arr::except($this->data, ['items'])
            );

            ProductMasterItem::where('product_master_id', $productMaster->id)->delete();

            foreach ($this->data['items'] as $item) {
                foreach ($item['product_ids'] as $productId) {
                    ProductMasterItem::create([
                        'product_master_id' => $productMaster->id,
                        'product_id'        => $productId,
                        'stock_conversion'  => $item['stock_conversion'],
                    ]);
                }
            }

            $this->form->fill();

            Notification::make()
                ->title('saved product master successfully')
                ->success()
                ->send();

            DB::commit();
            $this->redirect('/product-master');
        } catch (\Throwable $th) {
            DB::rollBack();
            Notification::make()
                ->title($th->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->action(function () {
                    $this->create();
                })
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Simpan')
                ->modalDescription('Apakah kamu yakin ingin menyimpan data ini?')
                ->modalSubmitActionLabel('Ya, simpan'),
        ];
    }
}
