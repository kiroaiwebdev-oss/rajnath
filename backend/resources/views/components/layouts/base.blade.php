@props(['title' => null, 'description' => null, 'robots' => 'index,follow', 'image' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#fdfbf6">
    <meta name="robots" content="{{ $robots }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ $site('brand.name') }}</title>
    @if ($description)<meta name="description" content="{{ $description }}">@endif
    <meta property="og:site_name" content="{{ $site('brand.name') }}">
    <meta property="og:title" content="{{ $title ?? $site('brand.name') }}">
    @if ($description)<meta property="og:description" content="{{ $description }}">@endif
    <meta property="og:image" content="{{ asset($image ?? 'images/owner/portrait-navy-960.jpg') }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="manifest" href="{{ route('manifest') }}">
    <link rel="icon" href="/icons/icon-192.png" type="image/png">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body {{ $attributes->merge(['class' => 'min-h-dvh']) }}>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 btn-primary">Skip to content</a>
    {{ $slot }}
</body>
</html>
