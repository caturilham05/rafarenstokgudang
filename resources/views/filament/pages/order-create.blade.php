<x-filament-panels::page>
    {{ $this->form }}

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('focus-input', ({ id }) => {
                requestAnimationFrame(() => document.getElementById(id)?.focus());
            });
        }, { once: true });
    </script>
</x-filament-panels::page>
