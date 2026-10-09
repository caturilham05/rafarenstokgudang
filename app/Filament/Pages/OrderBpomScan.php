<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Notifications\Notification;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ProductMaster;
use App\Models\ProductMasterItem;
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

                $productMaster = ProductMaster::query()
                    ->where('bpom_barcode', $this->bpom_barcode)
                    ->lockForUpdate()
                    ->first();

                if (!$productMaster) {
                    throw new \Exception("Product barcode [{$this->bpom_barcode}] is not registered in system.");
                }

                $orderProduct = OrderProduct::query()
                    ->join('product_master_items as pmi', function ($join) use ($productMaster) {
                        $join->on('pmi.product_id', '=', 'order_products.product_id')
                            ->where('pmi.product_master_id', $productMaster->id);
                    })
                    ->leftJoin('order_product_bpom_scans as scans', function ($join) use ($productMaster) {
                        $join->on('scans.order_product_id', '=', 'order_products.id')
                            ->where('scans.product_master_id', $productMaster->id);
                    })
                    ->where('order_products.order_id', $order->id)
                    ->whereRaw('COALESCE(scans.scanned_qty, 0) < order_products.qty * pmi.stock_conversion')
                    ->select('order_products.*')
                    ->lockForUpdate()
                    ->first();

                if (!$orderProduct) {
                    throw new \Exception("No matching unchecked product found in this order for barcode [{$this->bpom_barcode}].");
                }

                $masterItem = ProductMasterItem::query()
                    ->where('product_master_id', $productMaster->id)
                    ->where('product_id', $orderProduct->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$masterItem) {
                    throw new \Exception("Stock conversion is not configured for product [{$orderProduct->product_name}].");
                }

                if ($productMaster->stock < 1) {
                    throw new \Exception("Insufficient stock of Product Master [{$productMaster->product_name}].");
                }

                $requiredQty = (int) $orderProduct->qty * (int) $masterItem->stock_conversion;
                $masterScannedQty = (int) DB::table('order_product_bpom_scans')
                    ->where('order_product_id', $orderProduct->id)
                    ->where('product_master_id', $productMaster->id)
                    ->lockForUpdate()
                    ->value('scanned_qty');
                if ($masterScannedQty >= $requiredQty) {
                    throw new \Exception("No remaining scan required for product [{$orderProduct->product_name}].");
                }

                $productMaster->decrement('stock');

                DB::table('order_product_bpom_scans')->updateOrInsert(
                    [
                        'order_product_id' => $orderProduct->id,
                        'product_master_id' => $productMaster->id,
                    ],
                    ['scanned_qty' => $masterScannedQty + 1]
                );

                $checkedQty = (int) $orderProduct->bpom_checked_qty + 1;
                $orderProduct->update([
                    'bpom_checked_qty' => $checkedQty,
                ]);

                $masterConversions = DB::table('product_master_items')
                    ->select('product_id')
                    ->selectRaw('SUM(stock_conversion) as total_conversion')
                    ->groupBy('product_id');

                $unmappedProduct = DB::table('order_products as op')
                    ->leftJoinSub($masterConversions, 'pmi', 'pmi.product_id', '=', 'op.product_id')
                    ->where('op.order_id', $order->id)
                    ->whereNull('pmi.total_conversion')
                    ->value('op.product_name');

                if ($unmappedProduct) {
                    throw new \Exception("Product [{$unmappedProduct}] is not registered in Product Master.");
                }

                $remaining = (int) DB::table('order_products as op')
                    ->join('product_master_items as pmi', 'pmi.product_id', '=', 'op.product_id')
                    ->leftJoin('order_product_bpom_scans as scans', function ($join) {
                        $join->on('scans.order_product_id', '=', 'op.id')
                            ->on('scans.product_master_id', '=', 'pmi.product_master_id');
                    })
                    ->where('op.order_id', $order->id)
                    ->selectRaw('COALESCE(SUM(GREATEST(op.qty * pmi.stock_conversion - COALESCE(scans.scanned_qty, 0), 0)), 0) as remaining')
                    ->value('remaining');

                if ($remaining === 0) {
                    $orderProducts = OrderProduct::query()
                        ->where('order_id', $order->id)
                        ->with('product:id,product_name,stock')
                        ->lockForUpdate()
                        ->get();

                    foreach ($orderProducts as $item) {
                        if (!$item->product || $item->product->stock < $item->qty) {
                            throw new \Exception("Stock not sufficient for product [{$item->product_name}].");
                        }
                    }

                    foreach ($orderProducts as $item) {
                        Product::whereKey($item->product_id)->decrement('stock', $item->qty);
                    }

                    OrderProduct::query()
                        ->where('order_id', $order->id)
                        ->update(['is_bpom_checked' => true]);

                    $order->update(['bpom_checked_at' => now()]);
                }

                return [
                    'remaining' => $remaining,
                    'checked_qty' => $checkedQty,
                    'qty' => $requiredQty,
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
            DB::transaction(function () {
                $order = Order::query()
                    ->whereKey($this->current_order_id)
                    ->where('bpom_user_id', auth()->id())
                    ->whereNull('bpom_checked_at')
                    ->lockForUpdate()
                    ->first();

                if (!$order) {
                    return;
                }

                $scans = DB::table('order_product_bpom_scans as scans')
                    ->join('order_products as op', 'op.id', '=', 'scans.order_product_id')
                    ->where('op.order_id', $order->id)
                    ->select('scans.product_master_id')
                    ->selectRaw('SUM(scans.scanned_qty) as scanned_qty')
                    ->groupBy('scans.product_master_id')
                    ->get();

                foreach ($scans as $scan) {
                    ProductMaster::whereKey($scan->product_master_id)
                        ->increment('stock', $scan->scanned_qty);
                }

                DB::table('order_product_bpom_scans')
                    ->whereIn('order_product_id', OrderProduct::query()
                        ->select('id')
                        ->where('order_id', $order->id))
                    ->delete();

                $order->orderProducts()->update([
                    'bpom_checked_qty' => 0,
                    'is_bpom_checked' => false,
                ]);

                $order->update([
                    'bpom_user_id' => null,
                    'bpom_user_name' => null,
                ]);
            });
        }

        $this->reset(['waybill', 'bpom_barcode', 'current_order_id']);
    }

    public function getCurrentOrderProperty()
    {
        if (!$this->current_order_id) return null;
        return Order::with(['orderProducts.productMasters'])->find($this->current_order_id);
    }

    public function getCompletedOrdersProperty()
    {
        return Order::query()
            ->with('orderProducts.productMasters')
            ->where('bpom_user_id', auth()->id())
            ->whereDate('bpom_checked_at', today())
            ->orderByDesc('bpom_checked_at')
            ->paginate(15);
    }
}
