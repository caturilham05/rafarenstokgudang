<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\ProductMaster AS ProductMasterModel;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextInputColumn;
use App\Filament\Pages\ProductMasterCreate;
use Filament\Actions\Action as ActionsAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\DB;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;

class ProductMaster extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected string $view                                       = 'filament.pages.product-master';
    protected static ?string $navigationLabel                    = 'Product Masters';
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string | \UnitEnum | null $navigationGroup  = 'Product';
    protected static ?int $navigationSort                        = 2;

    protected function getTableQuery(): Builder
    {
        return ProductMasterModel::query()
            ->join('products', 'products.id', '=', 'product_masters.product_id')
            ->select('product_masters.*');
    }

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                ExportAction::make()
                    ->label('Export Product Master')
                    ->exports([
                        ExcelExport::make()
                            ->askForFilename()
                            ->withColumns([
                                Column::make('product_name')->heading('Product Name Master'),
                                Column::make('product.product_name')->heading('Product Name Marketplace'),
                                Column::make('stock')->heading('Stock'),
                                Column::make('stock_conversion')->heading('Stock Conversion'),
                            ]),
                    ]),

                ActionsAction::make('create')
                    ->label('Product Master Create')
                    ->icon('heroicon-o-plus')
                    ->url(ProductMasterCreate::getUrl()),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Product Master Name'),

                TextInputColumn::make('product_name')
                    ->tooltip('press enter to change product name')
                    ->rules(['required']),

                Tables\Columns\TextColumn::make('product.product_name')
                    ->label('Product Name Online'),

                TextInputColumn::make('stock')
                    ->tooltip('press enter to change stock')
                    ->rules(['required', 'integer', 'min:0']),

                TextInputColumn::make('stock_conversion')
                    ->tooltip('press enter to change stock conversion')
                    ->rules(['required', 'integer', 'min:0']),

            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //filter product name
                Filter::make('product')
                    ->label('Product')
                    ->schema([
                        Select::make('product_name')
                            ->label('Product')
                            ->searchable()
                            ->placeholder('Select product...')
                            ->getSearchResultsUsing(function (string $search) {
                                return DB::table('product_masters')
                                    ->select('product_name')
                                    ->where('product_name', 'like', "%{$search}%")
                                    ->groupBy('product_name')
                                    ->orderBy('product_name')
                                    ->limit(20)
                                    ->pluck('product_name', 'product_name')
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(fn ($value) => $value),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $query->when(
                            $data['product_name'] ?? null,
                            fn ($q, $value) =>
                                $q->where('product_masters.product_name', $value)
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return empty($data['product_name'])
                            ? null
                            : 'Product: ' . $data['product_name'];
                    }),
            ]);
    }

}
