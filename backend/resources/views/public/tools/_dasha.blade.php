@php $V = \App\Domain\Astro\Vedic::class; $today = now()->format('Y-m-d'); @endphp
<div class="card mt-6 p-5">
    <h3 class="text-lg">Vimshottari dasha</h3>
    <p class="mt-1 text-sm text-ink-600">Balance of the first Mahadasha at birth: {{ $d['balance_years'] }} years.</p>
    <ol class="mt-4 space-y-2">
        @foreach ($d['periods'] as $p)
            @php $now = $p['start'] <= $today && $today < $p['end']; @endphp
            <li><details class="rounded-xl border p-3 {{ $now ? 'border-gold-500 bg-gold-50' : 'border-ink-200' }}" @if ($now) open @endif>
                <summary class="cursor-pointer text-sm"><span class="font-semibold">{{ $V::PLANET_NAMES[$p['lord']] }}</span> {{ \Illuminate\Support\Carbon::parse($p['start'])->format('M Y') }} – {{ \Illuminate\Support\Carbon::parse($p['end'])->format('M Y') }} @if ($now)<span class="badge-gold ml-1">Current</span>@endif</summary>
                <ul class="mt-2 grid gap-1 text-xs sm:grid-cols-3">
                    @foreach ($p['antardasha'] as $a)<li @class(['font-semibold text-ink-900' => $a['start'] <= $today && $today < $a['end'], 'text-ink-600' => ! ($a['start'] <= $today && $today < $a['end'])])>{{ ucfirst($a['lord']) }}: {{ \Illuminate\Support\Carbon::parse($a['start'])->format('M Y') }}–{{ \Illuminate\Support\Carbon::parse($a['end'])->format('M Y') }}</li>@endforeach
                </ul>
            </details></li>
        @endforeach
    </ol>
</div>
