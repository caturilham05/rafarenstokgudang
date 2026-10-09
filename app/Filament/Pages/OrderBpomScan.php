<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Notifications\Notification;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\ProductMaster;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\DB;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Livewire\WithPagination;

class OrderBpomScan extends Page implements HasForms
{
    use Forms\Concerns\InteractsWithForms, HasPageShield, WithPagination;
    public function mount(): void
    {
        $order = Order::query()
            ->where('bpom_user_id', auth()->id())
            ->whereNull('bpom_checked_at')
            ->whereIn('status', ['PROCESSED', 'AWAITING_COLLECTION'])
            ->whereHas('orderProducts', fn ($query) => $query->where('is_bpom_checked', false))
            ->latest('id')
            ->first();

        $this->current_order_id = $order?->id;
        $this->waybill = $order?->waybill;
    }

    protected static ?string $navigationLabel                    = 'Product Scan';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-qr-code';
    protected static string | \UnitEnum | null $navigationGroup  = 'Order';
    protected static ?int $navigationSort                        = 7;
    protected string $view                                       = 'filament.pages.order-bpom-scan';

    public ?string $waybill       = null;
    public ?string $bpom_barcode  = null;
    public ?int $current_order_id = null;
    public bool $isScanning       = false;

    protected function getFormSchema(): array
    {
        return [
            TextInput::make('waybill')
                ->label('Scan Waybill (Resi)')
                ->placeholder('Scan waybill here...')
                ->autofocus()
                ->reactive()
                ->disabled($this->current_order_id !== null || $this->isScanning)
                ->extraAttributes([
                    'wire:keydown.enter' => 'submitWaybill',
                    'wire:loading.attr'  => 'disabled',
                    'wire:target'        => 'submitWaybill',
                ]),

            TextInput::make('bpom_barcode')
                ->label('Scan Product Barcode')
                ->placeholder('Scan product barcode here...')
                ->visible(fn() => $this->current_order_id !== null)
                ->disabled($this->isScanning)
                ->autofocus()
                ->reactive()
                ->extraAttributes([
                    'wire:keydown.enter' => 'submitBpom',
                    'wire:loading.attr'  => 'disabled',
                    'wire:target'        => 'submitBpom',
                ]),
        ];
    }

    public function submitWaybill(): void
    {
        if ($this->isScanning) return;
        $this->isScanning = true;

        try {
            if (empty($this->waybill)) {
                throw new \Exception('Waybill cannot be empty');
            }

            $order = DB::transaction(function () {
                $userId = auth()->id();
                $user = \App\Models\User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();

                $activeOrder = Order::query()
                    ->where('bpom_user_id', $user->id)
                    ->whereNull('bpom_checked_at')
                    ->whereHas('orderProducts', fn ($query) => $query->where('is_bpom_checked', false))
                    ->first();

                if ($activeOrder && $activeOrder->waybill !== $this->waybill) {
                    throw new \Exception("Waybill [{$activeOrder->waybill}] is still in progress. Resume or reset it first.");
                }

                $order = Order::query()
                    ->where('waybill', $this->waybill)
                    ->lockForUpdate()
                    ->first();

                if (!$order) {
                    throw new \Exception("Waybill [{$this->waybill}] not found in system.");
                }

                if ($order->bpom_checked_at) {
                    throw new \Exception("Waybill [{$this->waybill}] has already passed product check.");
                }

                if (!in_array($order->status, ['PROCESSED', 'AWAITING_COLLECTION'])) {
                    throw new \Exception("Waybill status is [{$order->status}], cannot verify products.");
                }

                if ($order->bpom_user_id && (int) $order->bpom_user_id !== (int) $user->id) {
                    $processorName = $order->bpom_user_name ?: 'user lain';
                    throw new \Exception("Waybill [{$this->waybill}] is being processed by {$processorName}.");
                }

                $order->update([
                    'bpom_user_id' => $user->id,
                    'bpom_user_name' => $user->name,
                ]);

                return $order;
            });

            $this->current_order_id = $order->id;

            Notification::make()
                ->title('Order Loaded')
                ->success()
                ->body("Waybill [{$order->waybill}] loaded. Please scan product barcodes.")
                ->send();

            $this->dispatch('playSound', type: 'success');
        } catch (\Throwable $th) {
            $this->dispatch('playSound', type: 'error');
            Notification::make()
                ->title($th->getMessage())
                ->danger()
                ->send();
            $this->reset('waybill');
        } finally {
            $this->isScanning = false;
        }
    }

    public function submitBpom(): void
    {
        if ($this->isScanning || !$this->current_order_id) return;
        $this->isScanning = true;

        try {
            if (empty($this->bpom_barcode)) {
                throw new \Exception('Product barcode cannot be empty');
            }

            $scanProgress = DB::transaction(function () {
                $order = Order::query()
                    ->whereKey($this->current_order_id)
                    ->lockForUpdate()
                    ->first();

                if (!$order || (int) $order->bpom_user_id !== (int) auth()->id() || $order->bpom_checked_at) {
                    throw new \Exception('This order is not assigned to your product scan process.');
                }

                $productMaster = ProductMaster::where('bpom_barcode', $this->bpom_barcode)->first();

                if (!$productMaster) {
                    throw new \Exception("Product barcode [{$this->bpom_barcode}] is not registered in system.");
                }

                $productIds = $productMaster->products()->pluck('products.id');

                $orderProduct = OrderProduct::query()
                    ->where('order_id', $order->id)
                    ->whereIn('product_id', $productIds)
                    ->where('is_bpom_checked', false)
                    ->whereColumn('bpom_checked_qty', '<', 'qty')
                    ->lockForUpdate()
                    ->first();

                if (!$orderProduct) {
                    throw new \Exception("No matching unchecked product found in this order for barcode [{$this->bpom_barcode}].");
                }

                $checkedQty = (int) $orderProduct->bpom_checked_qty + 1;
                $orderProduct->update([
                    'bpom_checked_qty' => $checkedQty,
                    'is_bpom_checked' => $checkedQty >= (int) $orderProduct->qty,
                ]);

                $remaining = OrderProduct::query()
                    ->where('order_id', $order->id)
                    ->get(['qty', 'bpom_checked_qty'])
                    ->sum(fn ($item) => max(0, (int) $item->qty - (int) $item->bpom_checked_qty));

                if ($remaining === 0) {
                    $order->update(['bpom_checked_at' => now()]);
                }

                return [
                    'remaining' => $remaining,
                    'checked_qty' => $checkedQty,
                    'qty' => (int) $orderProduct->qty,
                ];
            });

            if ($scanProgress['remaining'] === 0) {
                Notification::make()
                    ->title('Product Check Completed!')
                    ->success()
                    ->body("All products for this order have been successfully verified.")
                    ->send();

                $this->dispatch('playSound', type: 'success');
                $this->reset(['waybill', 'bpom_barcode', 'current_order_id']);
            } else {
                Notification::make()
                    ->title('Product Verified')
                    ->success()
                    ->body("Product scan {$scanProgress['checked_qty']}/{$scanProgress['qty']} complete. {$scanProgress['remaining']} unit(s) remaining.")
                    ->send();

                $this->dispatch('playSound', type: 'success');
                $this->reset('bpom_barcode');
            }

        } catch (\Throwable $th) {
            $this->dispatch('playSound', type: 'error');
            Notification::make()
                ->title($th->getMessage())
                ->danger()
                ->send();
            $this->reset('bpom_barcode');
        } finally {
            $this->isScanning = false;
        }
    }

    public function resetOrder(): void
    {
        if ($this->current_order_id) {
            Order::query()
                ->whereKey($this->current_order_id)
                ->where('bpom_user_id', auth()->id())
                ->whereNull('bpom_checked_at')
                ->update([
                    'bpom_user_id' => null,
                    'bpom_user_name' => null,
                ]);
        }

        $this->reset(['waybill', 'bpom_barcode', 'current_order_id']);
    }

    public function getCurrentOrderProperty()
    {
        if (!$this->current_order_id) return null;
        return Order::with(['orderProducts.productMaster'])->find($this->current_order_id);
    }

    public function getCompletedOrdersProperty()
    {
        return Order::query()
            ->with('orderProducts.productMaster')
            ->where('bpom_user_id', auth()->id())
            ->whereDate('bpom_checked_at', today())
            ->orderByDesc('bpom_checked_at')
            ->paginate(15);
    }
}
