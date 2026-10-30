@props(['title' => null, 'description' => null, 'robots' => 'index,follow', 'image' => null, 'jsonld' => null, 'type' => 'website'])
@php
    $locale = app()->getLocale();
    $brand = $site('brand.name');
    $fullTitle = $title ? $title.' · '.$brand : $brand.' — '.__('Vedic astrologer in Patna');
    $indexable = str_starts_with($robots, 'index');
    $langs = \App\Http\Middleware\SetLocale::enabled();
    // Canonical: the clean path, plus ?lang for a non-English page; other query strings are dropped.
    $langUrl = fn (string $l) => $l === 'en' ? url()->current() : url()->current().'?lang='.$l;
    $ogLocale = ['en' => 'en_IN', 'hi' => 'hi_IN', 'bn' => 'bn_IN', 'gu' => 'gu_IN', 'kn' => 'kn_IN', 'ml' => 'ml_IN', 'mr' => 'mr_IN', 'pa' => 'pa_IN', 'ta' => 'ta_IN', 'te' => 'te_IN'];
    $img = asset($image ?? 'images/owner/portrait-navy-960.jpg');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#fdfbf6">
    <meta name="robots" content="{{ $indexable ? $robots.',max-image-preview:large,max-snippet:-1,max-video-preview:-1' : $robots }}">
    <title>{{ $fullTitle }}</title>
    @if ($description)<meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 300) }}">@endif
    <meta name="author" content="{{ $site('brand.practitioner_name') }}">
    @if ($indexable)
        <link rel="canonical" href="{{ $langUrl($locale) }}">
        @if (count($langs) > 1)
            @foreach ($langs as $l)<link rel="alternate" hreflang="{{ $l }}" href="{{ $langUrl($l) }}">@endforeach
            <link rel="alternate" hreflang="x-default" href="{{ url()->current() }}">
        @endif
    @endif
    <meta property="og:type" content="{{ $type }}">
    <meta property="og:site_name" content="{{ $brand }}">
    <meta property="og:title" content="{{ $title ?? $brand }}">
    @if ($description)<meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 300) }}">@endif
    <meta property="og:url" content="{{ $langUrl($locale) }}">
    <meta property="og:locale" content="{{ $ogLocale[$locale] ?? 'en_IN' }}">
    @foreach ($langs as $l)@if ($l !== $locale)<meta property="og:locale:alternate" content="{{ $ogLocale[$l] ?? $l }}">@endif @endforeach
    <meta property="og:image" content="{{ $img }}">
    <meta property="og:image:alt" content="{{ $title ?? $brand }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title ?? $brand }}">
    @if ($description)<meta name="twitter:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 200) }}">@endif
    <meta name="twitter:image" content="{{ $img }}">
    <meta name="geo.region" content="IN-BR">
    <meta name="geo.placename" content="Patna">
    @if ($v = $site('seo.google_verification'))<meta name="google-site-verification" content="{{ $v }}">@endif
    @if ($v = $site('seo.bing_verification'))<meta name="msvalidate.01" content="{{ $v }}">@endif
    <link rel="manifest" href="{{ route('manifest') }}">
    <link rel="icon" href="/icons/icon-192.png" type="image/png">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <link rel="alternate" type="text/plain" title="LLM summary" href="{{ url('/llms.txt') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($indexable && $jsonld !== false){!! \App\Support\Seo\JsonLd::render(is_array($jsonld) ? $jsonld : []) !!}@endif
</head>
<body {{ $attributes->merge(['class' => 'min-h-dvh']) }}>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 btn-primary">{{ __('Skip to content') }}</a>
    {{ $slot }}
</body>
</html>
