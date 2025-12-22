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
use Illuminate\Support\Facades\DB;

class ProductMasterCreate extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

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

        if ($this->record) {
            $this->form->fill([
                ...$this->record->toArray(),
                'product_ids' => $this->record
                    ->items()
                    ->pluck('product_id')
                    ->toArray(),
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
            Forms\Components\Select::make('product_ids')
                ->label('Product Marketplace')
                ->multiple()
                ->searchable()
                ->columns(2)
                ->options(
                    Product::query()
                        ->pluck('product_name', 'id')
                )
                ->helperText('Hanya product yang belum terhubung ke master')
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
            if ($this->record) {
                $this->record->update($this->data);
                $productMaster = $this->record;
            } else {
                $productMaster = ProductMaster::create($this->data);
            }

            ProductMasterItem::where('product_master_id', $productMaster->id)->delete();

            foreach ($this->data['product_ids'] as $product_id) {
                ProductMasterItem::create([
                    'product_master_id' => $productMaster->id,
                    'product_id'        => $product_id,
                ]);
            }

            $this->form->fill();

            Notification::make()
                ->title('saved product master successfully')
                ->success()
                ->send();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            Notification::make()
                ->title($th->getMessage())
                ->danger()
                ->send();
        }
    }
}
