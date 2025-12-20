<x-filament::page>
    <form wire:submit.prevent="create" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit" style="margin-top: 1rem">
            Simpan
        </x-filament::button>
    </form>
</x-filament::page>
