<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Notifications\Notification;
use App\Models\Order;
use App\Models\Packer;
use App\Models\Product;
use App\Models\ProductMaster;
use App\Models\ProductMasterItem;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\QueryException;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\DB;

// 375 = TO_CONFIRM_RECEIVE
// 1482 = SHIPPED

class OrderScan extends Page implements HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationLabel                    = 'Order Scan';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-qr-code';
    protected static string | \UnitEnum | null $navigationGroup  = 'Order';
    protected static ?int $navigationSort                        = 3;
    protected string $view                                       = 'filament.pages.order-scan';

    public ?string $barcode     = null;
    public ?int $packer_id      = null;
    public array $scannedOrders = [];

    public function updatedPackerId($value): void
    {
        if (empty($value)) {
            $this->scannedOrders = [];
            return;
        }

        $this->scannedOrders = Order::with('orderProducts.product')
            ->where('status', 'SCANNING')
            ->where('packer_id', $value)
            ->orderBy('updated_at')
            ->get()
            ->all();
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('packer_id')
                ->label('Packer Name')
                ->options(
                    Packer::query()->pluck('packer_name', 'id')
                )
                ->searchable()
                ->reactive()
                ->required(),

            TextInput::make('barcode')
                ->label('Scan Waybill')
                ->placeholder('Scan barcode waybill here...')
                ->helperText('Please fill in the barcode such as [waybill]')
                ->autofocus()
                ->required()
                ->reactive()
                ->extraAttributes([
                    'wire:keydown.enter' => 'submitScan'
                ]),
        ];
    }

    public function submitScan(): void
    {
        DB::beginTransaction();

        try {
            if (empty($this->barcode)) {
                throw new \Exception('barcode cannot be empty');
            }

            $order = Order::with('orderProducts.product')
                ->where('waybill', $this->barcode)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw new \Exception("waybill [{$this->barcode}] not found in system");
            }

            if ($order->status !== 'PROCESSED') {
                throw new \Exception(
                    "waybill [{$order->waybill}] cannot be scanned, current status is [{$order->status}]"
                );
            }

            if (!empty($order->packer_id)) {
                throw new \Exception(
                    "waybill [{$order->waybill}] already assigned to packer [{$order->packer_name}]"
                );
            }

            // =====================================
            // AMBIL SEMUA PRODUCT ID DI ORDER
            // =====================================
            $productIds = $order->orderProducts
                ->pluck('product_id')
                ->unique()
                ->values();

            // =====================================
            // VALIDASI PRODUCT TERDAFTAR DI MASTER
            // =====================================
            $registeredProductIds = ProductMasterItem::whereIn('product_id', $productIds)
                ->pluck('product_id')
                ->unique();

            $unregisteredProductIds = $productIds->diff($registeredProductIds);

            if ($unregisteredProductIds->isNotEmpty()) {
                $productNames = Product::whereIn('id', $unregisteredProductIds)
                    ->pluck('product_name')
                    ->implode(', ');

                throw new \Exception(
                    "The following products are not yet registered in Product Master: {$productNames}. Please add Product Master first"
                );
            }

            // =====================================
            // LOCK PRODUCT MASTER ITEMS (+ MASTER)
            // =====================================
            $productMasterItems = ProductMasterItem::with('productMaster')
                ->whereIn('product_id', $productIds)
                ->lockForUpdate()
                ->get();

            // =====================================
            // HITUNG PENGURANGAN STOCK MASTER
            // product_master_id => total_reduction
            // (PAKAI stock_conversion DARI ITEM)
            // =====================================
            $masterReductions = [];

            foreach ($order->orderProducts as $orderItem) {
                foreach ($productMasterItems->where('product_id', $orderItem->product_id) as $masterItem) {

                    // FIX: conversion dari product_master_items
                    $reduceQty = $orderItem->qty * $masterItem->stock_conversion;

                    $masterReductions[$masterItem->product_master_id]
                        = ($masterReductions[$masterItem->product_master_id] ?? 0) + $reduceQty;
                }
            }

            // =====================================
            // LOCK & UPDATE PRODUCT MASTER
            // =====================================
            $productMasters = ProductMaster::whereIn('id', array_keys($masterReductions))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($masterReductions as $masterId => $reduceQty) {
                $master = $productMasters[$masterId];

                if ($master->stock < $reduceQty) {
                    throw new \Exception(
                        "Insufficient stock of Product Master [{$master->product_name}]"
                    );
                }

                ProductMaster::where('id', $masterId)
                    ->where('stock', '>=', $reduceQty)
                    ->decrement('stock', $reduceQty);
            }

            // =====================================
            // DECREMENT STOCK PRODUCT (MARKETPLACE)
            // =====================================
            foreach ($order->orderProducts as $item) {
                $affected = Product::where('id', $item->product_id)
                    ->where('stock', '>=', $item->qty)
                    ->decrement('stock', $item->qty);

                if ($affected === 0) {
                    throw new \Exception(
                        "Stock not sufficient for product {$item->product->product_name}"
                    );
                }
            }

            // =====================================
            // UPDATE ORDER
            // =====================================
            $packer = Packer::findOrFail($this->packer_id);

            $order->update([
                'packer_id'   => $packer->id,
                'packer_name' => $packer->packer_name,
                'status'      => 'SCANNING',
            ]);

            $order = $order->fresh('orderProducts.product');

            // =====================================
            // SIMPAN KE LIST SCAN
            // =====================================
            if (collect($this->scannedOrders)->contains('id', $order->id)) {
                throw new \Exception("waybill already scanned in this session");
            }

            $this->scannedOrders[] = $order;

            Notification::make()
                ->title('Scan berhasil')
                ->success()
                ->body(
                    "waybill [{$order->waybill}] from invoice [{$order->invoice}] scanned successfully"
                )
                ->send();

            $this->reset('barcode');
            DB::commit();

        } catch (\Throwable $th) {
            DB::rollBack();

            Notification::make()
                ->title($th->getMessage())
                ->danger()
                ->send();

            $this->reset('barcode');
        }
    }

    public function submitAll(): void
    {
        if (empty($this->scannedOrders)) {
            Notification::make()
                ->title('No order to submit')
                ->warning()
                ->send();
            return;
        }

        DB::beginTransaction();

        try {
            $orderIds = collect($this->scannedOrders)->pluck('id')->toArray();

            // lock semua order
            $orders = Order::whereIn('id', $orderIds)
                ->lockForUpdate()
                ->get();

            foreach ($orders as $order) {

                if ($order->status !== 'SCANNING') {
                    throw new \Exception(
                        "Order {$order->waybill} status invalid ({$order->status})"
                    );
                }

                $order->update([
                    'status' => 'SCANNED',
                ]);
            }

            DB::commit();

            Notification::make()
                ->title('Submit Success')
                ->success()
                ->body(count($orders) . ' order success submited')
                ->send();

            // reset list
            $this->scannedOrders = [];

        } catch (\Throwable $th) {
            DB::rollBack();

            Notification::make()
                ->title($th->getMessage())
                ->danger()
                ->send();
        }
    }

    public function confirmSubmit()
    {
        Notification::make()
            ->title('Submit all order?')
            ->body('Status want to change SCANNED')
            ->warning()
            ->actions([
                Action::make('submit')
                    ->label('Yes, Submit')
                    ->button()
                    ->action('submitAll'),
            ])
            ->send();
    }
}
