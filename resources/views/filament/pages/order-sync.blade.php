<x-filament-panels::page>
    <style>
        .order-sync-form { display: grid; gap: 1.5rem; }
        .order-sync-fields { display: grid; gap: 1.25rem; }
        .order-sync-label { display: block; margin-bottom: .5rem; font-size: .875rem; font-weight: 600; }
        .order-sync-help { font-size: .875rem; color: #64748b; line-height: 1.6; }
        .dark .order-sync-help { color: #94a3b8; }
        .order-sync-error { margin-top: .375rem; font-size: .875rem; color: #dc2626; }
        .dark .order-sync-error { color: #fca5a5; }
        .order-sync-footer { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; padding-top: 1.25rem; border-top: 1px solid #e2e8f0; }
        .dark .order-sync-footer { border-color: #374151; }
        @media (min-width: 768px) { .order-sync-fields { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    </style>

    <x-filament::section icon="heroicon-o-arrow-path">
        <x-slot name="heading">Sinkron resi &amp; status order</x-slot>
        <x-slot name="description">
            Perbarui order Shopee dan TikTok yang belum memiliki resi. Pilih marketplace dan tanggal order di bawah ini.
        </x-slot>

        <form method="POST" action="{{ route('marketplace.orders.sync') }}" class="order-sync-form"
            x-data="{ submitting: false }" x-on:submit="submitting = true" x-bind:aria-busy="submitting">
            @csrf
            <p class="order-sync-error"><strong>Penting:</strong> Pastikan order sudah masuk ke sistem.</p>
            <div class="order-sync-fields">
                <div>
                    <label for="marketplace" class="order-sync-label">Marketplace</label>
                    <x-filament::input.wrapper :valid="!$errors->has('marketplace')">
                        <x-filament::input.select id="marketplace" name="marketplace">
                            <option value="all" @selected(old('marketplace', 'all') === 'all')>Semua marketplace</option>
                            <option value="shopee" @selected(old('marketplace') === 'shopee')>Shopee</option>
                            <option value="tiktok" @selected(old('marketplace') === 'tiktok')>TikTok</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    @error('marketplace') <p role="alert" class="order-sync-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="date_start" class="order-sync-label">Tanggal order</label>
                    <x-filament::input.wrapper :valid="!$errors->has('date_start')">
                        <x-filament::input id="date_start" name="date_start" type="date" required
                            :value="old('date_start', now()->toDateString())" aria-describedby="sync-date-help" />
                    </x-filament::input.wrapper>
                    @error('date_start') <p role="alert" class="order-sync-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <p id="sync-date-help" class="order-sync-help">
                Menggunakan tanggal order ({{ config('app.timezone') }}). Setiap sinkron hanya memproses satu hari, pukul 00:00–23:59.
            </p>
            <div class="order-sync-footer">
                <p class="order-sync-help">Proses berjalan di latar belakang. Anda dapat melanjutkan aktivitas lain.</p>
                <x-filament::button type="submit" icon="heroicon-o-arrow-path" x-bind:disabled="submitting">
                    <span x-text="submitting ? 'Mengirim permintaan…' : 'Mulai sinkron'">Mulai sinkron</span>
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
