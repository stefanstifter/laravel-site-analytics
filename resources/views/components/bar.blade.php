@props([
    'label',
    'value',
    'share',
])

<div {{ $attributes }}>
    <div class="flex items-baseline justify-between gap-4 text-sm">
        <span class="truncate text-zinc-700 dark:text-zinc-300">{{ $label }}</span>
        <span class="whitespace-nowrap tabular-nums text-zinc-500 dark:text-zinc-400">{{ $value }}</span>
    </div>
    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
        <div class="h-full rounded-full bg-blue-600 dark:bg-blue-400" style="width: {{ max(0, min(100, (int) $share)) }}%"></div>
    </div>
</div>
