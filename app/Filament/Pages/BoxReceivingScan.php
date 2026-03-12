<?php

namespace App\Filament\Pages;

use App\Models\Box;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Livewire\WithPagination;

class BoxReceivingScan extends Page implements HasForms
{
    use InteractsWithForms, HasPageShield, WithPagination;

    protected static ?string $navigationLabel = 'Box Scan';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-qr-code';
    protected static string | \UnitEnum | null $navigationGroup  = 'Box';


    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.box-receiving-scan';

    public ?array $data     = [];
    public bool $isScanning = false;


    public function mount(): void
    {
        $this->form->fill([
            'qty'    => 1,
            'qty_in' => 1
        ]);
    }

    protected function getFormStatePath(): string
    {
        return 'data';
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('sku')
                ->label('Scan SKU')
                ->placeholder('Scan barcode SKU here...')
                // ->helperText('Please fill in the barcode such as [waybill]')
                ->autofocus()
                ->required()
                ->disabled(fn () => $this->isScanning)
                ->extraAttributes([
                    // barcode gun friendly
                    // 'wire:model.defer'   => 'barcode',

                    // hanya submit saat ENTER
                    'wire:keydown.enter' => 'scan',
                    'wire:model.live' => 'data.sku',

                    // auto lock selama proses
                    'wire:loading.attr'  => 'disabled',
                    'wire:target'        => 'scan',
                ]),
        ];
    }

    public function scan()
    {
        if ($this->isScanning) {
            return;
        }

        $this->isScanning = true;

        try {

            $sku    = $this->data['sku'] ?? null;
            $qty    = $this->data['qty'] ?? 1;
            $qty_in = $this->data['qty_in'] ?? 1;

            if (!$sku) {
                throw new \Exception('Please scan SKU');
            }

            $box = Box::firstOrCreate(
                ['sku' => $sku],
                [
                    'qty'    => 0,
                    'qty_in' => 0
                ]
            );

            $box->increment('qty', $qty);
            $box->increment('qty_in', $qty_in);

            Notification::make()
                ->title("Scan Success: {$sku}")
                ->success()
                ->send();

            $this->form->fill([
                'sku'    => '',
                'qty'    => 1,
                'qty_in' => 1
            ]);

        } catch (\Throwable $th) {

            Notification::make()
                ->title($th->getMessage())
                ->danger()
                ->send();

        } finally {
            $this->isScanning = false;
        }
    }

    public function getScannedBoxesProperty()
    {
        return Box::query()
            ->select('id','sku', 'qty', 'qty_in', 'created_at')
            ->whereDate('created_at', now())
            ->orderByDesc('id')
            ->paginate(5);
    }


    public function getTotalQtyProperty()
    {
        return Box::query()
            ->whereDate('created_at', now())
            ->sum('qty_in');
    }
}
