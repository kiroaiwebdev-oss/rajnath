{{-- North Indian diamond chart. $houses = [1..12 => ['sign'=>int,'planets'=>[keys]]], $label --}}
@php
    $abbr = ['sun' => 'Su', 'moon' => 'Mo', 'mars' => 'Ma', 'mercury' => 'Me', 'jupiter' => 'Ju', 'venus' => 'Ve', 'saturn' => 'Sa', 'rahu' => 'Ra', 'ketu' => 'Ke'];
    // Text anchor point for each house in a 300×300 box (house 1 at top centre, anticlockwise).
    $pos = [1 => [150, 80], 2 => [75, 38], 3 => [38, 75], 4 => [80, 150], 5 => [38, 225], 6 => [75, 262], 7 => [150, 220], 8 => [225, 262], 9 => [262, 225], 10 => [220, 150], 11 => [262, 75], 12 => [225, 38]];
    $desc = collect($houses)->map(fn ($h, $i) => 'House '.$i.': sign '.($h['sign'] + 1).(count($h['planets']) ? ', '.implode(', ', array_map('ucfirst', $h['planets'])) : ''))->implode('; ');
@endphp
<figure class="mx-auto w-full max-w-xs">
    <svg viewBox="0 0 300 300" role="img" aria-label="{{ $label }}. {{ $desc }}" class="w-full rounded-xl bg-white">
        <g fill="none" stroke="#2b356f" stroke-width="1.5">
            <rect x="2" y="2" width="296" height="296"/><path d="M2 2 L298 298 M298 2 L2 298 M150 2 L2 150 L150 298 L298 150 Z"/>
        </g>
        @foreach ($houses as $i => $h)
            <text x="{{ $pos[$i][0] }}" y="{{ $pos[$i][1] - 14 }}" text-anchor="middle" font-size="10" fill="#8a6514">{{ $h['sign'] + 1 }}</text>
            <text x="{{ $pos[$i][0] }}" y="{{ $pos[$i][1] + 2 }}" text-anchor="middle" font-size="12" font-weight="600" fill="#141a3c">{{ implode(' ', array_map(fn ($p) => $abbr[$p], array_slice($h['planets'], 0, 3))) }}</text>
            @if (count($h['planets']) > 3)<text x="{{ $pos[$i][0] }}" y="{{ $pos[$i][1] + 16 }}" text-anchor="middle" font-size="12" font-weight="600" fill="#141a3c">{{ implode(' ', array_map(fn ($p) => $abbr[$p], array_slice($h['planets'], 3))) }}</text>@endif
        @endforeach
    </svg>
    <figcaption class="mt-2 text-center text-sm text-ink-600">{{ $label }} · numbers show signs (1 = Mesha)</figcaption>
</figure>
