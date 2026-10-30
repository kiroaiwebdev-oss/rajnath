@props(['rating', 'size' => 'size-4'])
<span {{ $attributes->merge(['class' => 'stars']) }} role="img" aria-label="{{ __(':n out of 5 stars', ['n' => $rating]) }}">
    @for ($i = 1; $i <= 5; $i++)
        <svg aria-hidden="true" class="{{ $size }} {{ $i <= round($rating) ? '' : 'text-ink-200' }}" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.9l-5.2 2.7 1-5.8L1.5 7.7l5.9-.9z"/></svg>
    @endfor
</span>
