@props([
    'title',
    'padded' => true,
])

<section {{ $attributes->merge(['class' => 'sa-panel']) }}>
    <div class="sa-panel-header">
        <h2 class="sa-panel-title">{{ $title }}</h2>
        @isset($actions)
            <div class="sa-panel-actions">{{ $actions }}</div>
        @endisset
    </div>

    <div @class(['sa-panel-body' => $padded])>
        {{ $slot }}
    </div>
</section>
