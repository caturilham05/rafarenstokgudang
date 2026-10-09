<x-filament-panels::page>
    <style>
        .bpom-order-items {
            margin-top: 24px;
        }

        .bpom-reset-button {
            margin-top: 16px;
        }

        /* Info Waybill / Invoice / Customer */
        .bpom-order-info {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
            padding: 16px;
            border-radius: 12px;
            background-color: rgba(128, 128, 128, 0.08);
            font-size: 14px;
        }

        .bpom-order-info dt {
            font-weight: 500;
            color: #6b7280;
        }

        .bpom-order-info dd {
            margin: 4px 0 0 0;
            font-weight: 600;
        }

        @media (max-width: 640px) {
            .bpom-order-info {
                grid-template-columns: 1fr;
            }
        }

        /* Tabel */
        .bpom-table-wrapper {
            overflow-x: auto;
            border: 1px solid rgba(128, 128, 128, 0.3);
            border-radius: 12px;
            padding: 0 8px;
        }

        .bpom-order-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0 10px;
            font-size: 14px;
        }

        .bpom-order-table th,
        .bpom-order-table td {
            padding: 12px 16px;
            vertical-align: middle;
        }

        .bpom-order-table th {
            font-weight: 600;
        }

        /* Kolom 1: Product Name (kiri) */
        .bpom-order-table th:nth-child(1),
        .bpom-order-table td:nth-child(1) {
            text-align: left;
        }

        /* Kolom 2: Qty (tengah, header & body sejajar) */
        .bpom-order-table th:nth-child(2),
        .bpom-order-table td:nth-child(2) {
            text-align: center;
        }

        /* Kolom 3: Product Status (tengah, header & body sejajar) */
        .bpom-order-table th:nth-child(3),
        .bpom-order-table td:nth-child(3) {
            text-align: center;
        }

        /* Pastikan badge Filament berada di tengah kolom status */
        .bpom-order-table th:nth-child(3),
        .bpom-order-table td:nth-child(3) {
            text-align: left;
        }

        .bpom-order-table th:nth-child(4),
        .bpom-order-table td:nth-child(4),
        .bpom-order-table td:nth-child(4) .fi-badge {
            text-align: center;
        }

        .bpom-order-table td:nth-child(4) .fi-badge {
            display: inline-flex;
        }

        .bpom-completed-table th:nth-child(3),
        .bpom-completed-table td:nth-child(3) {
            text-align: center;
        }

        .bpom-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 16px;
            color: #6b7280;
            font-size: 13px;
        }

        .bpom-pagination-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .bpom-pagination-button {
            min-height: 36px;
            padding: 0 12px;
            border: 1px solid rgba(128, 128, 128, 0.35);
            border-radius: 8px;
            background: transparent;
            color: inherit;
            font-size: 13px;
            line-height: 1;
            cursor: pointer;
        }

        .bpom-pagination-button:hover:not(:disabled) {
            background: rgba(128, 128, 128, 0.1);
        }

        .bpom-pagination-button:disabled {
            cursor: not-allowed;
            opacity: 0.45;
        }

        @media (max-width: 480px) {
            .bpom-pagination {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        .bpom-products-modal {
            position: fixed;
            inset: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: rgba(3, 7, 18, 0.5);
        }

        .bpom-products-modal[x-cloak] {
            display: none;
        }

        .bpom-products-modal-panel {
            width: 100%;
            max-width: 672px;
            max-height: 85vh;
            overflow-y: auto;
            padding: 24px;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
        }

        .dark .bpom-products-modal-panel {
            background: rgb(17, 24, 39);
        }

        .bpom-products-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 16px;
        }

        .bpom-products-modal-title {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .bpom-products-modal-close {
            border: 0;
            background: transparent;
            color: #6b7280;
            font-size: 26px;
            line-height: 1;
            cursor: pointer;
        }

        .bpom-products-modal-close:hover {
            color: #111827;
        }

        .dark .bpom-products-modal-close:hover {
            color: #e5e7eb;
        }

        .bpom-products-modal-table-wrapper {
            max-height: 60vh;
            overflow: auto;
        }

        .bpom-products-modal-table td:first-child,
        .bpom-products-modal-table td:last-child {
            text-align: left;
        }

        .bpom-products-modal-table th:nth-child(2),
        .bpom-products-modal-table td:nth-child(2) {
            text-align: center;
        }
    </style>

    <div class="w-full">
        <x-filament::section>
            <x-slot name="heading">Scan Waybill &amp; Product Barcode</x-slot>

            {{ $this->form }}

            @if ($this->currentOrder)
                <div class="bpom-reset-button flex justify-end">
                    <x-filament::button color="danger" wire:click="resetOrder">
                        Reset / Cancel Current Order
                    </x-filament::button>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section class="bpom-order-items">
            <x-slot name="heading">Order Items Status</x-slot>

            @if ($order = $this->currentOrder)
                <dl class="bpom-order-info">
                    <div>
                        <dt>Waybill</dt>
                        <dd>{{ $order->waybill }}</dd>
                    </div>
                    <div>
                        <dt>Invoice</dt>
                        <dd>{{ $order->invoice }}</dd>
                    </div>
                </dl>

                <div class="bpom-table-wrapper">
                    <table class="bpom-order-table">
                        <colgroup>
                            <col style="width: 48%">
                            <col style="width: 8%">
                            <col style="width: 24%">
                            <col style="width: 20%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col">Product Name</th>
                                <th scope="col">Qty</th>
                                <th scope="col">Product Master</th>
                                <th scope="col">Product Scan Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->orderProducts as $item)
                                <tr>
                                    <td style="overflow: hidden;">
                                        <div x-data="{ expanded: false }">
                                            <button type="button" @click="expanded = !expanded" style="display: block; width: 100%; text-align: left; cursor: pointer;" :title="expanded ? 'Click to collapse' : 'Click to view full text'">
                                                <span x-show="!expanded" style="display: block;">
                                                    {{ \Illuminate\Support\Str::limit($item->product_name, 50) }}
                                                    @if (\Illuminate\Support\Str::length($item->product_name) > 50)
                                                        <span style="color: #2563eb;">Lihat selengkapnya</span>
                                                    @endif
                                                </span>
                                                <span x-show="expanded" x-cloak style="display: block; white-space: normal; word-break: break-word;">
                                                    {{ $item->product_name }}
                                                    <span style="color: #2563eb;">Lihat lebih sedikit</span>
                                                </span>
                                            </button>
                                        </div>
                                    </td>
                                    <td>{{ $item->qty }}</td>
                                    <td>{{ $item->productMaster?->product_name ?? '—' }}</td>
                                    <td>
                                        @if ($item->is_bpom_checked)
                                            <x-filament::badge color="success">Passed (Lolos)</x-filament::badge>
                                        @else
                                            <x-filament::badge color="warning">Scanned {{ $item->bpom_checked_qty }}/{{ $item->qty }}</x-filament::badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="rounded-xl border border-dashed border-gray-300 px-4 py-10 text-center dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Please scan a waybill first to view order items.</p>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section class="bpom-order-items">
            <x-slot name="heading">Completed Product Orders Today</x-slot>

            @php($completedOrders = $this->completedOrders)

            @if ($completedOrders->isNotEmpty())
                <div
                    x-data="{ showProducts: false, selectedProducts: [] }"
                    @keydown.escape.window="showProducts = false"
                >
                <div class="bpom-table-wrapper">
                    <table class="bpom-order-table bpom-completed-table">
                        <thead>
                            <tr>
                                <th scope="col">Waybill</th>
                                <th scope="col">Invoice</th>
                                <th scope="col">Waktu Scan Produk</th>
                                <th scope="col">Lihat Produk</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($completedOrders as $order)
                                <tr>
                                    <td>{{ $order->waybill }}</td>
                                    <td>{{ $order->invoice }}</td>
                                    <td>{{ $order->bpom_checked_at->locale('id')->translatedFormat('H:i') }}</td>
                                    <td>
                                        <x-filament::button
                                            size="sm"
                                            x-on:click="selectedProducts = {{ \Illuminate\Support\Js::from($order->orderProducts->map(fn ($item) => [
                                                'name' => $item->product_name,
                                                'qty' => $item->qty,
                                                'master' => $item->productMaster?->product_name ?? '—',
                                            ])->values()) }}; showProducts = true"
                                        >
                                            Lihat Produk
                                        </x-filament::button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div
                    x-cloak
                    x-show="showProducts"
                    x-transition.opacity
                    class="bpom-products-modal"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="bpom-products-modal-heading"
                    x-on:click.self="showProducts = false"
                >
                    <div class="bpom-products-modal-panel">
                        <div class="bpom-products-modal-header">
                            <h2 id="bpom-products-modal-heading" class="bpom-products-modal-title">Produk Order</h2>
                            <button
                                type="button"
                                class="bpom-products-modal-close"
                                aria-label="Tutup modal"
                                x-on:click="showProducts = false"
                            >
                                &times;
                            </button>
                        </div>

                        <div class="bpom-table-wrapper bpom-products-modal-table-wrapper">
                            <table class="bpom-order-table bpom-products-modal-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Produk Order</th>
                                        <th scope="col">Qty</th>
                                        <th scope="col">Produk Master</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(product, index) in selectedProducts" :key="index">
                                        <tr>
                                            <td x-text="product.name"></td>
                                            <td x-text="product.qty"></td>
                                            <td x-text="product.master"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                </div>

                <div class="bpom-pagination">
                    <span>
                        Menampilkan {{ $completedOrders->firstItem() }}–{{ $completedOrders->lastItem() }} dari {{ $completedOrders->total() }} order
                    </span>
                    <div class="bpom-pagination-controls">
                        <button
                            type="button"
                            class="bpom-pagination-button"
                            wire:click="previousPage"
                            wire:loading.attr="disabled"
                            @disabled($completedOrders->onFirstPage())
                        >
                            Sebelumnya
                        </button>
                        <span>Halaman {{ $completedOrders->currentPage() }} dari {{ $completedOrders->lastPage() }}</span>
                        <button
                            type="button"
                            class="bpom-pagination-button"
                            wire:click="nextPage"
                            wire:loading.attr="disabled"
                            @disabled(!$completedOrders->hasMorePages())
                        >
                            Selanjutnya
                        </button>
                    </div>
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">No product orders have been completed today.</p>
            @endif
        </x-filament::section>
    </div>

    <script>
        document.addEventListener('livewire:init', () => {
            const sounds = {
                success: new Audio('/sounds/success.wav'),
                empty: new Audio('/sounds/error-empty.wav'),
                duplicate: new Audio('/sounds/error-duplicate.wav'),
                error: new Audio('/sounds/error-empty.wav'),
            };

            Livewire.on('playSound', (event) => {
                const type = event.type ?? 'error';

                if (sounds[type]) {
                    sounds[type].currentTime = 0;
                    sounds[type].play().catch(() => {});
                }
            });
        });
    </script>
</x-filament-panels::page>
