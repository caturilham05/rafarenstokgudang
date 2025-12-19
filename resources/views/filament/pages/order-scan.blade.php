<x-filament::page>
    <style>
        .container {
            background: #ffffff;
            max-width: 1200px;
            margin: auto;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px 40px;
            margin-bottom: 32px;
        }

        .info-item label {
            font-weight: 600;
            display: block;
            margin-bottom: 6px;
            color: #374151;
        }

        .info-item div {
            line-height: 1.6;
        }

        h3 {
            margin-bottom: 16px;
        }

        .table-wrapper {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f3f4f6;
        }

        th, td {
            padding: 14px 16px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        th {
            text-align: left;
            font-weight: 600;
            color: #374151;
        }

        .success {
            color: #16a34a;
            font-weight: 600;
        }

        .accordion {
            margin-top: 24px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 12px 16px;
        }

        .accordion summary {
            font-weight: 600;
            cursor: pointer;
            list-style: none;
        }

        .accordion summary::-webkit-details-marker {
            display: none;
        }

        .accordion-content {
            margin-top: 12px;
            color: #4b5563;
        }

        .h1-custom{
            display: flex;
            justify-content: center;
            font-size: 30px;
            font-weight: bold;
            margin-top: 1rem;
            margin-bottom: 1rem;
        }
    </style>
    {{ $this->form }}

    @if ($this->scannedOrder)
        <div class="container">
            <div class="h1-custom">
                <h1>ORDER DETAIL</h1>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <label>Invoice</label>
                    <div>{{$this->scannedOrder->invoice}}</div>
                </div>

                <div class="info-item">
                    <label>Waybill</label>
                    <div>{{$this->scannedOrder->waybill}}</div>
                </div>

                <div class="info-item">
                    <label>Packer</label>
                    <div>{{$this->scannedOrder->packer_name}}</div>
                </div>

                <div class="info-item">
                    <label>Total Price</label>
                    <div>{{$this->scannedOrder->total_price}}</div>
                </div>

                <div class="info-item">
                    <label>Marketplace Fee</label>
                    <div>{{$this->scannedOrder->marketplace_fee}}</div>
                </div>

                <div class="info-item">
                    <label>Status</label>
                    <div>{{$this->scannedOrder->status}}</div>
                </div>

                <div class="info-item">
                    <label>Store Name</label>
                    <div>{{$this->scannedOrder->store_name}}</div>
                </div>

                <div class="info-item">
                    <label>Courier</label>
                    <div>{{$this->scannedOrder->courier}}</div>
                </div>

                <div class="info-item">
                    <label>Buyer Username</label>
                    <div>{{$this->scannedOrder->buyer_username}}</div>
                </div>

                <div class="info-item">
                    <label>Customer Name</label>
                    <div>{{$this->scannedOrder->customer_name}}</div>
                </div>

                <div class="info-item">
                    <label>Customer Phone</label>
                    <div>{{$this->scannedOrder->customer_phone}}</div>
                </div>

                <div class="info-item">
                    <label>Customer Address</label>
                    <div>{{$this->scannedOrder->customer_address}}</div>
                </div>

                <div class="info-item">
                    <label>Qty</label>
                    <div>{{$this->scannedOrder->qty}}</div>
                </div>

                <div class="info-item">
                    <label>Shipping Costs</label>
                    <div>{{$this->scannedOrder->shipping_cost}}</div>
                </div>

                <div class="info-item">
                    <label>Notes</label>
                    <div>{{$this->scannedOrder->notes}}</div>
                </div>

                <div class="info-item">
                    <label>Payment Method</label>
                    <div>{{$this->scannedOrder->payment_method}}</div>
                </div>

                <div class="info-item">
                    <label>Order date</label>
                    <div>{{$this->scannedOrder->order_time}}</div>
                </div>
            </div>

            {{-- <div class="h1-custom">
                <h3>ORDER PRDOUCTS</h3>
            </div> --}}
            <div class="table-wrapper">
                <table>
                    <thead>
                    <tr>
                        <th>Product</th>
                        <th>Varian</th>
                        <th>Qty</th>
                        <th>Sale</th>
                        <th>Sale Total</th>
                    </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->scannedOrder->orderProducts as $item )
                            <tr>
                                <td>
                                    {{$item->product_name}}
                                </td>
                                <td>
                                    {{$item->varian}}
                                </td>
                                <td>
                                    {{$item->qty}}
                                </td>
                                <td>
                                    {{$item->sale}}
                                </td>
                                <td class="success">
                                    {{($item->qty ?? 0) * ($item->sale ?? 0)}}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <details class="accordion">
                <summary>Marketplace Fee Detail</summary>
                <div class="accordion-content">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Voucher From Seller</label>
                            <div>{{$this->scannedOrder->voucher_from_seller}}</div>
                        </div>

                        <div class="info-item">
                            <label>Seller Order Processing Fee</label>
                            <div>{{$this->scannedOrder->seller_order_processing_fee}}</div>
                        </div>

                        <div class="info-item">
                            <label>Service Fee</label>
                            <div>{{$this->scannedOrder->service_fee}}</div>
                        </div>

                        <div class="info-item">
                            <label>Premi</label>
                            <div>{{$this->scannedOrder->delivery_seller_protection_fee_premium_amount}}</div>
                        </div>

                        <div class="info-item">
                            <label>Commision Fee</label>
                            <div>{{$this->scannedOrder->commission_fee}}</div>
                        </div>
                    </div>
                </div>
            </details>

        </div>
        {{-- <div class="mt-6 space-y-6">

            <h2>
                <center>
                    View order
                </center>
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-6">
                <div class="space-y-4">
                    @include('filament.pages.partials.order-item', [
                        'label' => 'Invoice',
                        'value' => $this->scannedOrder->invoice,
                    ])

                    @include('filament.pages.partials.order-item', [
                        'label' => 'Total price',
                        'badge' => true,
                        'badgeColor' => 'success',
                        'value' => 'IDR ' . number_format($this->scannedOrder->total_price, 2),
                    ])

                    @include('filament.pages.partials.order-item', [
                        'label' => 'Status',
                        'badge' => true,
                        'badgeColor' => match($this->scannedOrder->status) {
                            'PROCESSED' => 'warning',
                            'COMPLETED' => 'success',
                            'CANCELLED' => 'danger',
                            default => 'gray',
                        },
                        'value' => $this->scannedOrder->status,
                    ])

                    @include('filament.pages.partials.order-item', [
                        'label' => 'Courier',
                        'value' => $this->scannedOrder->courier,
                    ])

                    @include('filament.pages.partials.order-item', [
                        'label' => 'Customer name',
                        'value' => $this->scannedOrder->customer_name,
                    ])

                    @include('filament.pages.partials.order-item', [
                        'label' => 'Customer address',
                        'value' => $this->scannedOrder->customer_address,
                        'multiline' => true,
                    ])

                    @include('filament.pages.partials.order-item', [
                        'label' => 'Shipping cost',
                        'value' => 'IDR ' . number_format($this->scannedOrder->shipping_cost, 2),
                    ])
                </div>
                <div class="space-y-4">
                    @include('filament.pages.partials.order-item', [
                        'label' => 'Waybill',
                        'value' => $this->scannedOrder->waybill,
                    ])

                    @include('filament.pages.partials.order-item', [
                        'label' => 'Marketplace Fee',
                        'badge' => true,
                        'badgeColor' => 'danger',
                        'value' => 'IDR ' . number_format($this->scannedOrder->marketplace_fee, 2),
                        'helper' => 'Click Marketplace Fee Details below the Order Products for more information.',
                    ])

                    @include('filament.pages.partials.order-item', [
                        'label' => 'Store Name',
                        'value' => optional(
                            $this->scannedOrder->orderProducts->first()?->product?->store
                        )->store_name ?? '-',
                    ])

                    @include('filament.pages.partials.order-item', [
                        'label' => 'Buyer username',
                        'value' => $this->scannedOrder->buyer_username,
                    ])

                    @include('filament.pages.partials.order-item', [
                        'label' => 'Customer phone',
                        'value' => $this->scannedOrder->customer_phone,
                    ])

                    @include('filament.pages.partials.order-item', [
                        'label' => 'Qty',
                        'value' => $this->scannedOrder->qty,
                    ])

                    @include('filament.pages.partials.order-item', [
                        'label' => 'Notes',
                        'value' => $this->scannedOrder->notes,
                        'multiline' => true,
                    ])
                </div>
            </div>

            <div style="padding-top: 1rem">
                <h3 class="font-semibold text-lg mb-4">
                    Order Products
                </h3>

                <div class="border rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-left">Product</th>
                                <th class="px-4 py-2 text-left">Varian</th>
                                <th class="px-4 py-2 text-right">Qty</th>
                                <th class="px-4 py-2 text-right">Sale</th>
                                <th class="px-4 py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->scannedOrder->orderProducts as $item)
                                <tr class="border-t">
                                    <td class="px-4 py-2">
                                        {{ $item->product->product_name }}
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ $item->product->varian }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        {{ $item->qty }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        IDR {{ number_format($item->sale) }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-semibold text-green-600">
                                        IDR {{ number_format($item->qty * $item->sale) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div> --}}
    @endif


    {{-- @if ($scannedOrder)
        <x-filament::section
            class="mt-6"
            heading="Order Detail"
            icon="heroicon-o-document-text"
        >
            <div class="grid grid-cols-2 gap-4 mb-4 text-sm">
                <div><strong>Invoice:</strong> {{ $scannedOrder->invoice }}</div>
                <div><strong>Resi:</strong> {{ $scannedOrder->waybill }}</div>
                <div><strong>Status:</strong> {{ $scannedOrder->status }}</div>
                <div><strong>Scanned At:</strong> {{ $scannedOrder->scanned_at }}</div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left">Produk</th>
                            <th class="px-3 py-2 text-left">Varian</th>
                            <th class="px-3 py-2 text-right">Qty</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($scannedOrder->orderProducts as $item)
                            <tr>
                                <td class="px-3 py-2">
                                    {{ $item->product->product_name ?? '-' }}
                                </td>
                                <td class="px-3 py-2">
                                    {{ $item->variant_name }}
                                </td>
                                <td class="px-3 py-2 text-right font-semibold">
                                    {{ $item->qty }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif --}}

    <script>
        document.addEventListener('livewire:load', () => {
            setInterval(() => {
                const input = document.querySelector('input[name="barcode"]');
                if (input) input.focus();
            }, 500);
        });
    </script>
</x-filament::page>
