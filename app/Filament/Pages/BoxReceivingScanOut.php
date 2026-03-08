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


class BoxReceivingScanOut extends Page
{
    use InteractsWithForms, HasPageShield, WithPagination;

    protected static ?string $navigationLabel = 'Box Scan Out';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-qr-code';
    protected static string | \UnitEnum | null $navigationGroup  = 'Box';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.box-receiving-scan-out';

    public ?array $data     = [];
    public bool $isScanning = false;

    public function mount(): void
    {
        $this->form->fill([
            'qty'     => 1,
            'qty_out' => 1
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

            $sku              = $this->data['sku'] ?? null;
            $qty              = $this->data['qty'] ?? 1;
            $qty_out          = $this->data['qty_out'] ?? 1;
            $last_scanned_out = now();

            if (!$sku) {
                throw new \Exception('Please scan SKU');
            }

            // cek sku ada atau tidak
            $box = Box::where('sku', $sku)->first();

            if (!$box) {
                throw new \Exception("SKU {$sku} not found");
            }

            // cek qty habis
            if ($box->qty <= 0) {
                throw new \Exception("Insufficient stock SKU {$sku}");
            }

            $box = Box::firstOrCreate(
                ['sku' => $sku],
                [
                    'qty'     => 0,
                    'qty_out' => 0,
                ]
            );

            $box->increment('qty_out', $qty_out, [
                'last_scanned_out' => now()
            ]);

            $box->decrement('qty', $qty);

            Notification::make()
                ->title("Scan Out Success: {$sku}")
                ->success()
                ->send();

            $this->form->fill([
                'sku'              => '',
                'qty'              => 1,
                'qty_out'          => 1,
                'last_scanned_out' => $last_scanned_out
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
            ->select('id','sku', 'qty', 'qty_out', 'last_scanned_out')
            ->whereDate('last_scanned_out', now())
            ->orderByDesc('id')
            ->paginate(5);
    }

    public function getTotalQtyOutProperty()
    {
        return Box::query()
            ->whereDate('last_scanned_out', now())
            ->sum('qty_out');
    }
}
