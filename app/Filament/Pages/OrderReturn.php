<?php

namespace App\Filament\Pages;

use App\Models\OrderReturn as OrderReturnModels;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use pxlrbt\FilamentExcel\Actions\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class OrderReturn extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected string $view = 'filament.pages.order-return';
    protected static ?string $navigationLabel                    = 'Order Return';
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::ArrowUturnLeft;
    protected static string | \UnitEnum | null $navigationGroup  = 'Order';
    protected static ?int $navigationSort                        = 5;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OrderReturnModels::query()->with('order')
            )
            ->headerActions([
                ExportAction::make()
                    ->label('Export Order Returns')
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->askForFilename()
                            ->withColumns([
                                Column::make('invoice_order')->heading('Invoice Order'),
                                Column::make('invoice_return')->heading('Invoice Return'),
                                Column::make('waybill')->heading('Tracking Number'),
                                Column::make('order.marketplace_name')->heading('Marketplace Name'),
                                Column::make('order.store_name')->heading('Store Name'),
                                Column::make('reason')->heading('Reason'),
                                Column::make('reason_text')->heading('Reason Text'),
                                Column::make('refund_amount')->heading('Refund Amount'),
                                Column::make('return_time')->heading('REturn Time'),
                                Column::make('status')->heading('Status'),
                            ]),
                    ]),
            ])
            ->columns([
                TextColumn::make('invoice_order')
                    ->label('Invoice Order'),

                    TextColumn::make('invoice_return')
                    ->label('Invoice Return'),

                TextColumn::make('waybill')
                    ->label('Tracking Number')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('order.marketplace_name')
                    ->label('Marketplace Name')
                    ->toggleable(),

                TextColumn::make('order.store_name')
                    ->label('Store Name')
                    ->toggleable(),

                TextColumn::make('courier')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reason')
                    ->toggleable(),

                TextColumn::make('reason_text')
                    ->label('Reason Text')
                    ->limit(30)
                    ->toggleable()
                    ->tooltip(fn ($state) => $state),

                TextColumn::make('status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'JUDGING'  => 'warning',
                    'ACCEPTED' => 'success',
                    default    => 'warning',
                })
                ->toggleable(),


                TextColumn::make('status_logistic')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'LOGISTICS_DELIVERY_DONE' => 'success',
                    default                   => 'warning',
                })
                ->toggleable(isToggledHiddenByDefault: true),


                TextColumn::make('buyer_username')
                    ->label('Buyer Username')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('refund_amount')
                    ->money('IDR')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('return_time')
                    ->label('Return Time')
                    ->dateTime('j F Y H:i:s', 'Asia/Jakarta'),
            ])
            ->defaultSort('return_time', 'desc')
            ->filters([
                //filter invoice
                // Filter::make('invoice_order')
                //     ->label('Invoice')
                //     ->schema([
                //         TextInput::make('invoice_order')
                //             ->placeholder('Search Invoice Order...'),
                //     ])
                //     ->query(function (Builder $query, array $data) {
                //         $query->when(
                //             $data['invoice_order'] ?? null,
                //             fn ($q, $value) =>
                //                 $q->where('invoice_order', $value)
                //         );
                //     })
                //     ->indicateUsing(function (array $data): ?string {
                //         return empty($data['invoice_order']) ? null : $data['invoice_order'];
                //     })

                Filter::make('invoice')
                    ->label('Invoice')
                    ->schema([
                        TextInput::make('invoice')
                            ->placeholder('Search invoice order / return...'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (empty($data['invoice'])) {
                            return;
                        }

                        $value = $data['invoice'];

                        $query->where(function (Builder $q) use ($value) {
                            $q->where('invoice_order', 'like', "%{$value}%")
                            ->orWhere('invoice_return', 'like', "%{$value}%");
                        });
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return empty($data['invoice'])
                            ? null
                            : 'Invoice: ' . $data['invoice'];
                    }),

                Filter::make('store_name')
                    ->label('Store Name')
                    ->schema([
                        Select::make('store_name')
                            ->label('Store Name')
                            ->searchable()
                            ->placeholder('Select Store Name...')
                            ->getSearchResultsUsing(function (string $search) {
                                return DB::table('stores')
                                    ->select('store_name')
                                    ->where('store_name', 'like', "%{$search}%")
                                    ->groupBy('store_name')
                                    ->orderBy('store_name')
                                    ->limit(20)
                                    ->pluck('store_name', 'store_name')
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(fn ($value) => $value),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $query->when(
                            $data['store_name'] ?? null,
                            fn ($q, $value) =>
                            $q->whereHas('order', function ($oq) use ($value) {
                                $oq->where('store_name', $value);
                            })
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return empty($data['store_name'])
                            ? null
                            : 'Store Name: ' . $data['store_name'];
                    }),

                Filter::make('return_time')
                    ->label('Return Time')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From')
                            ->maxDate(now()),

                        DatePicker::make('until')
                            ->label('Until')
                            ->rule(fn (callable $get) =>
                                fn (string $attribute, $value, $fail) =>
                                    $get('from') && $value < $get('from')
                                        ? $fail('End date must not be earlier than start date.')
                                        : null
                            ),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $q, $date) =>
                                    $q->whereDate('return_time', '>=', $date)
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $q, $date) =>
                                    $q->whereDate('return_time', '<=', $date)
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (empty($data['from']) && empty($data['until'])) {
                            return null;
                        }

                        return 'Order Time: '
                            . (date('j F Y', strtotime($data['from'])) ?? 'Any')
                            . ' → '
                            . (date('j F Y', strtotime($data['until'])) ?? 'Any');
                    }),
            ]);
    }

}
