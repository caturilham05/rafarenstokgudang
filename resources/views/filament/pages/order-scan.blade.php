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

    @if (count($this->scannedOrders) > 0)
        <div class="container">
            <div class="table-wrapper">
                <table>
                    <thead>
                    <tr>
                        <th>Waybill</th>
                        <th>Packer Name</th>
                    </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->scannedOrders as $scannedOrder)
                            <tr>
                                <td>{{$scannedOrder->waybill}}</td>
                                <td>{{$scannedOrder->packer_name}}</td>
                            </tr>
                        @endforeach

                    </tbody>
                </table>
            </div>

            <div style="max-width:1200px;margin:16px auto;text-align:right">
                <button
                    type="button"
                    wire:click="submitAll"
                    wire:loading.attr="disabled"
                    wire:click="confirmSubmit"
                    style="
                        background:#16a34a;
                        color:white;
                        padding:12px 24px;
                        border-radius:8px;
                        font-weight:600;
                        cursor:pointer;
                    "
                >
                    SUBMIT ({{ count($this->scannedOrders) }} ORDER)
                </button>
            </div>
        </div>
    @endif
    <script>
        document.addEventListener('livewire:load', () => {
            setInterval(() => {
                const input = document.querySelector('input[name="barcode"]');
                if (input) input.focus();
            }, 500);
        });
    </script>
</x-filament::page>
