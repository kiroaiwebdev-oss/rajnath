@props(['title' => null, 'description' => null, 'robots' => 'index,follow'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#fdfbf6">
    <meta name="robots" content="{{ $robots }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ $site('brand.name') }}</title>
    @if ($description)<meta name="description" content="{{ $description }}">@endif
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body {{ $attributes->merge(['class' => 'min-h-dvh']) }}>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 btn-primary">Skip to content</a>
    {{ $slot }}
</body>
</html>
