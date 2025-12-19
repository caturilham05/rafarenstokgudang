<div>
    <div class="text-sm font-medium text-gray-600 dark:text-gray-400">
        {{ $label }}
    </div>

    <div class="mt-1">
        @isset($badge)
            <x-filament::badge color="{{ $badgeColor ?? 'gray' }}">
                {{ $value }}
            </x-filament::badge>
        @else
            <div class="{{ $multiline ?? false ? 'whitespace-pre-line' : '' }}">
                {{ $value }}
            </div>
        @endisset
    </div>

    @isset($helper)
        <div class="mt-1 text-xs text-gray-500">
            {{ $helper }}
        </div>
    @endisset
</div>
