<x-filament::widget>
    <style>
        .expedition-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .expedition-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 16px;
        }

        .expedition-item {
            background: #f9fafb;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            transition: all 0.2s ease;
            border: 1px solid #eee;
        }

        .expedition-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }

        .expedition-name {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 6px;
        }

        .expedition-total {
            font-size: 22px;
            font-weight: bold;
            color: #111827;
        }
    </style>
    <x-filament::card>
        <h2 class="expedition-title">
            Total Order By Ekspedisi
        </h2>

        <div class="expedition-grid">
            @foreach ($this->data as $item)
                <div class="expedition-item" wire:key="courier-{{ $item->courier }}-{{ $item->total }}">
                    <div class="expedition-name">
                        {{ $item->courier }}
                    </div>

                    <div class="expedition-total">
                        {{ number_format($item->total) }}
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::card>
</x-filament::widget>
