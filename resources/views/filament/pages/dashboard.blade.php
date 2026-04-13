<x-filament-panels::page>
    <style>
        .dashboard-date {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: inline-block;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .dashboard-date-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
        }

        .dashboard-date-value {
            font-size: 20px;
            font-weight: 600;
            color: #111827;
        }
    </style>

    <div class="dashboard-date">
        <div class="dashboard-date-label">
            Filter Tanggal
        </div>

        <div class="dashboard-date-value">
            {{ \Carbon\Carbon::parse($this->date)->translatedFormat('d F Y') }}
        </div>
    </div>
    <x-filament-widgets::widgets
        :widgets="\Filament\Facades\Filament::getWidgets()"
    />
</x-filament-panels::page>
