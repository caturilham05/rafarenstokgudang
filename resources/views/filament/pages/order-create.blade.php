<x-filament-panels::page>
    {{ $this->form }}

    @if ($input_type === 'date')
        <x-filament::button wire:click="submitOrderCreate" wire:loading.attr="disabled" wire:target="submitOrderCreate">
            Submit
        </x-filament::button>
    @endif

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('focus-input', ({ id }) => {
                requestAnimationFrame(() => document.getElementById(id)?.focus());
            });
        }, { once: true });
    </script>
</x-filament-panels::page>
