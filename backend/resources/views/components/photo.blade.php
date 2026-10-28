@props(['src', 'alt', 'sizes' => '100vw', 'eager' => false])
@php
    // Uses the WebP sibling when present; width/height come from the file to prevent layout shift.
    $path = public_path($src);
    $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $src);
    [$w, $h] = is_file($path) ? (getimagesize($path) ?: [null, null]) : [null, null];
@endphp
<picture>
    @if (is_file(public_path($webp)))<source type="image/webp" srcset="/{{ $webp }}">@endif
    <img src="/{{ $src }}" alt="{{ $alt }}" @if ($w) width="{{ $w }}" height="{{ $h }}" @endif
         @if ($eager) fetchpriority="high" @else loading="lazy" decoding="async" @endif
         {{ $attributes->merge(['class' => 'block']) }}>
</picture>
