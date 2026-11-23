{{-- Standalone page the reports render in when the "layout" config is null. --}}
@props([
    'title' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <style>
        html { color-scheme: light dark; }
        html:is(.dark, [data-theme="dark"]) { color-scheme: dark; }
        html:is(.light, [data-theme="light"]) { color-scheme: light; }
        body { margin: 0; background: light-dark(#fafafa, #09090b); font-family: ui-sans-serif, system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
    </style>
</head>
<body>
    {{ $slot }}
</body>
</html>
