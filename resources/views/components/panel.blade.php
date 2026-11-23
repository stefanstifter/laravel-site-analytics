@props([
    'title',
    'padded' => true,
])

<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900']) }}>
    <div class="flex items-center justify-between gap-4 border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
        <h2 class="text-sm font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ $title }}</h2>
        @isset($actions)
            <div class="text-sm">{{ $actions }}</div>
        @endisset
    </div>

    <div @class(['px-6 py-5' => $padded])>
        {{ $slot }}
    </div>
</section>
