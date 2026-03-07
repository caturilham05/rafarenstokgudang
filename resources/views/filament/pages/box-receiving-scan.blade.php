<x-filament-panels::page>
    {{ $this->form }}

    <script>
        document.addEventListener('focus-sku', () => {
            setTimeout(() => {
                document.querySelector('input[name="data.sku"]')?.focus()
            }, 50)
        })
    </script>
</x-filament-panels::page>
