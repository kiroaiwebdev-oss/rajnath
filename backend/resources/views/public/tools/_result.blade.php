@php $c = $r['chart'] ?? null; $V = \App\Domain\Astro\Vedic::class; @endphp
@if ($c)
    <div class="card p-5">
        <p class="text-sm text-ink-600">{{ $c['input']['name'] ? $c['input']['name'].' · ' : '' }}{{ \Illuminate\Support\Carbon::parse($c['input']['local'])->format('d M Y, g:i A') }} · {{ $c['input']['place'] }} ({{ $c['input']['tz'] }})</p>
        @if (($c['input']['tz_resolution'] ?? 'exact') !== 'exact')<p class="mt-2 text-sm text-warning-700">The clocks changed at this place on this date; please confirm the birth time.</p>@endif
        <dl class="mt-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl bg-ivory-100 p-3"><dt class="text-xs text-ink-600">Lagna (Ascendant)</dt><dd class="font-semibold">{{ $c['lagna']['sign_name'] }} {{ $c['lagna']['degree'] }}</dd></div>
            <div class="rounded-xl bg-ivory-100 p-3"><dt class="text-xs text-ink-600">Moon sign (Rashi)</dt><dd class="font-semibold">{{ $c['moon_sign']['name'] }}</dd></div>
            <div class="rounded-xl bg-ivory-100 p-3"><dt class="text-xs text-ink-600">Nakshatra</dt><dd class="font-semibold">{{ $c['nakshatra']['name'] }}, pada {{ $c['nakshatra']['pada'] }}</dd></div>
        </dl>
    </div>
@endif

@switch($r['tool'])
    @case('kundli')
    @case('lagna-navamsa')
        <div class="card mt-6 grid gap-6 p-5 md:grid-cols-2">
            @include('public.tools._chart', ['houses' => $c['houses'], 'label' => 'Rashi chart (D1)'])
            @include('public.tools._chart', ['houses' => $c['navamsa'], 'label' => 'Navamsa chart (D9)'])
        </div>
        <div class="table-wrap mt-6" role="region" aria-label="Planet positions" tabindex="0">
            <table class="table"><thead><tr><th scope="col">Planet</th><th scope="col">Sign</th><th scope="col">Degree</th><th scope="col">House</th><th scope="col">Nakshatra</th><th scope="col">Navamsa</th></tr></thead><tbody>
                @foreach ($c['planets'] as $k => $p) @continue(! $p['classical'])
                    <tr><td class="font-medium">{{ $p['name'] }}{{ $p['retro'] && ! in_array($k, ['rahu', 'ketu']) ? ' (R)' : '' }}</td><td>{{ $p['sign_name'] }}</td><td>{{ $p['degree'] }}</td><td>{{ $p['house'] }}</td><td>{{ $p['nakshatra'] }} {{ $p['pada'] }}</td><td>{{ $V::SIGNS[$p['navamsa_sign']] }}</td></tr>
                @endforeach
            </tbody></table>
        </div>
        @if ($r['tool'] === 'kundli')
            <div class="card mt-6 p-5">
                <h3 class="text-lg">Manglik check</h3>
                <p class="mt-2 text-sm">Mars is in house {{ $c['manglik']['mars_house_from']['lagna'] }} from Lagna, {{ $c['manglik']['mars_house_from']['moon'] }} from the Moon and {{ $c['manglik']['mars_house_from']['venus'] }} from Venus.
                    {{ $c['manglik']['is_manglik'] ? 'By the basic rule (houses 1, 2, 4, 7, 8, 12 from Lagna or Moon) this chart is Manglik.' : 'By the basic rule this chart is not Manglik.' }} Cancellation rules are not applied automatically — ask Acharya Ji for a full assessment.</p>
            </div>
            @include('public.tools._dasha', ['d' => $c['dasha']])
        @endif
        @break
    @case('memorable-periods')
        @include('public.tools._dasha', ['d' => $c['dasha']])
        @break
    @case('moon-sign')
        <div class="card mt-6 p-5"><p class="text-lg">Your Moon sign is <strong>{{ $c['moon_sign']['name'] }}</strong>. Its lord is {{ $V::PLANET_NAMES[$V::SIGN_LORDS[$c['moon_sign']['sign']]] }}.</p></div>
        @break
    @case('nakshatra')
        <div class="card mt-6 p-5"><p class="text-lg">Birth star: <strong>{{ $c['nakshatra']['name'] }}</strong>, pada {{ $c['nakshatra']['pada'] }}. Nakshatra lord: {{ $V::PLANET_NAMES[$c['nakshatra']['lord']] }} (this sets your first Vimshottari dasha).</p></div>
        @break
    @case('kundli-matching')
        @php $m = $r['match']; @endphp
        <div class="card p-5">
            <p class="text-sm text-ink-600">{{ $r['a']['input']['name'] ?: 'Boy' }}: {{ $r['a']['moon_sign']['name'] }}, {{ $r['a']['nakshatra']['name'] }} · {{ $r['b']['input']['name'] ?: 'Girl' }}: {{ $r['b']['moon_sign']['name'] }}, {{ $r['b']['nakshatra']['name'] }}</p>
            <p class="mt-3 text-3xl font-semibold">{{ rtrim(rtrim(number_format($m['total'], 1), '0'), '.') }} <span class="text-lg text-ink-600">/ 36 gunas</span></p>
            <p class="mt-1 text-sm">Traditionally {{ $m['band'] }}.@if ($m['doshas']) Doshas present: {{ implode(', ', $m['doshas']) }}.@endif</p>
        </div>
        <div class="table-wrap mt-6" role="region" aria-label="Koota scores" tabindex="0"><table class="table"><thead><tr><th scope="col">Koota</th><th scope="col">Details</th><th scope="col">Score</th></tr></thead><tbody>
            @foreach ($m['kootas'] as $k => $v)<tr><td class="font-medium">{{ ucwords(str_replace('_', ' ', $k)) }}</td><td>{{ $v['detail'] }}</td><td class="whitespace-nowrap">{{ $v['score'] }} / {{ $v['max'] }}</td></tr>@endforeach
        </tbody></table></div>
        <div class="card mt-6 p-5 text-sm">
            <p>Manglik: boy {{ $r['a']['manglik']['is_manglik'] ? 'yes' : 'no' }}, girl {{ $r['b']['manglik']['is_manglik'] ? 'yes' : 'no' }} (basic rule).</p>
            <p class="mt-2 text-ink-600">Guna milan is one traditional input. Exceptions (parihara), the 7th house and Navamsa should be reviewed by an astrologer. A score is never a reason to force or refuse a marriage.</p>
        </div>
        @break
    @case('lal-kitab')
        <div class="card mt-6 p-5">
            @include('public.tools._chart', ['houses' => collect($r['lal_kitab'])->map(fn ($pl, $i) => ['sign' => $i - 1, 'planets' => $pl])->all(), 'label' => 'Lal Kitab chart (Aries = house 1)'])
        </div>
        @break
    @case('sade-sati')
        <div class="card mt-6 p-5">
            <h3 class="text-lg">Sade Sati periods for Moon sign {{ $c['moon_sign']['name'] }}</h3>
            <ol class="mt-3 space-y-3 text-sm">
                @forelse ($r['sade_sati'] as $s)
                    @php $now = now()->format('Y-m-d'); $current = $s['start'] <= $now && $now <= $s['end']; @endphp
                    <li class="rounded-xl border p-3 {{ $current ? 'border-gold-500 bg-gold-50' : 'border-ink-200' }}">
                        <p class="font-semibold">{{ \Illuminate\Support\Carbon::parse($s['start'])->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($s['end'])->format('d M Y') }} @if ($current)<span class="badge-gold ml-1">Now</span>@endif</p>
                        <p class="text-ink-600">@foreach ($s['phases'] as $ph){{ ucfirst($ph['phase']) }} {{ \Illuminate\Support\Carbon::parse($ph['start'])->format('M Y') }}–{{ \Illuminate\Support\Carbon::parse($ph['end'])->format('M Y') }}@if (! $loop->last) · @endif @endforeach</p>
                    </li>
                @empty
                    <li>No Sade Sati in the 90 years from birth.</li>
                @endforelse
            </ol>
            <p class="mt-3 text-xs text-ink-600">Phases: rising (Saturn in the 12th from the Moon), peak (over the Moon), setting (2nd from the Moon). Retrograde returns are included.</p>
        </div>
        @break
    @case('pitra-dosh')
        <div class="card mt-6 p-5 text-sm">
            @if ($r['pitra']['indicators'])
                <p class="font-semibold">Traditional indicators found:</p>
                <ul class="mt-2 list-disc pl-5">@foreach ($r['pitra']['indicators'] as $i)<li>{{ $i }}</li>@endforeach</ul>
            @else
                <p>None of the common classical indicators were found (Sun with Rahu/Ketu/Saturn, Rahu/Ketu/Saturn in the 9th house, 9th lord with the nodes).</p>
            @endif
            <p class="mt-3 text-ink-600">Pitra dosh is assessed differently across traditions. This check is a starting point, not a verdict.</p>
        </div>
        @break
    @case('rahu-ketu')
        @php $n = $r['nodes']; @endphp
        <div class="card mt-6 p-5 text-sm">
            <p>Natal Rahu: <strong>{{ $n['natal']['rahu']['sign_name'] }}</strong>, house {{ $n['natal']['rahu']['house'] }} · Natal Ketu: <strong>{{ $n['natal']['ketu']['sign_name'] }}</strong>, house {{ $n['natal']['ketu']['house'] }}.</p>
            <p class="mt-2">Today Rahu transits {{ $n['transit']['rahu'] }} ({{ $n['transit']['rahu_house_from_lagna'] }}th from your Lagna, {{ $n['transit']['rahu_house_from_moon'] }}th from your Moon) and Ketu transits {{ $n['transit']['ketu'] }}.@if ($n['transit']['next_change']) Next sign change: {{ \Illuminate\Support\Carbon::parse($n['transit']['next_change'])->format('d M Y') }}.@endif</p>
        </div>
        @break
    @case('predictive-personality')
        <div class="mt-6 grid gap-4 md:grid-cols-2">
            @foreach (['lagna' => 'Lagna (how you meet the world)', 'moon' => 'Moon sign (your mind and feelings)'] as $k => $h)
                @php $x = $r['personality'][$k]; @endphp
                <div class="card p-5 text-sm"><h3 class="text-base">{{ $h }}</h3><p class="mt-1 font-semibold">{{ $x['sign'] }}</p>
                    <p class="text-ink-600">{{ $x['element'] }} · {{ $x['modality'] }} · lord {{ $x['lord'] }}</p>
                    <ul class="mt-2 list-disc pl-5">@foreach ($x['notes'] as $t)<li>{{ $t }}</li>@endforeach</ul></div>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-ink-600">These are classical sign qualities, not a psychological test.</p>
        @break
    @case('baby-name')
        <div class="card mt-6 p-5">
            <p>Moon nakshatra: <strong>{{ $c['nakshatra']['name'] }}</strong>, pada {{ $c['nakshatra']['pada'] }}.</p>
            <p class="mt-3 text-lg">Traditional starting sound for this pada: <strong>{{ $r['pada_syllable'] }}</strong></p>
            <p class="mt-1 text-sm text-ink-600">All four syllables of this nakshatra: {{ implode(', ', $r['syllables']) }}.</p>
            <p class="mt-3 text-xs text-ink-600">Syllable lists vary between traditions. For a researched name with meaning and numerology, see our Baby Name Report.</p>
        </div>
        @break
    @case('rudraksha')
        <div class="card mt-6 p-5"><p>Your Lagna lord is {{ $V::PLANET_NAMES[$r['rudraksha']['lagna_lord']] }}. The traditional Rudraksha for it is <strong>{{ $r['rudraksha']['mukhi'] }}</strong>.</p>
            <p class="mt-2 text-sm text-ink-600">Please consult before wearing; genuineness and the wearing procedure matter.</p></div>
        @break
    @case('numerology')
        <div class="card mt-6 grid gap-3 p-5 sm:grid-cols-3">
            <div class="rounded-xl bg-ivory-100 p-3"><p class="text-xs text-ink-600">Mulank (root)</p><p class="text-2xl font-semibold">{{ $r['mulank'] }}</p></div>
            <div class="rounded-xl bg-ivory-100 p-3"><p class="text-xs text-ink-600">Bhagyank (destiny)</p><p class="text-2xl font-semibold">{{ $r['bhagyank'] }}</p></div>
            <div class="rounded-xl bg-ivory-100 p-3"><p class="text-xs text-ink-600">Name number</p><p class="text-2xl font-semibold">{{ $r['name_number']['number'] }} <span class="text-sm text-ink-600">({{ $r['name_number']['total'] }})</span></p></div>
        </div>
        <p class="mt-3 text-sm">Ruling planet of {{ $r['mulank'] }}: {{ $V::PLANET_NAMES[\App\Domain\Astro\Numerology::PLANET[$r['mulank']]] }} · Day: {{ $r['traits']['day'] }} · Colours: {{ implode(', ', $r['traits']['colours']) }}</p>
        @break
    @case('name-number')
        <div class="card mt-6 p-5"><p class="text-lg">Name number: <strong>{{ $r['name_number']['number'] }}</strong> (total {{ $r['name_number']['total'] }})</p>
            <p class="mt-2 break-words text-sm text-ink-600">@foreach ($r['name_number']['letters'] as [$l, $v]){{ $l }}={{ $v }} @endforeach</p></div>
        @break
    @case('destiny-number')
        <div class="card mt-6 p-5"><p class="text-lg">Bhagyank (destiny number): <strong>{{ $r['bhagyank'] }}</strong> · Mulank: {{ $r['mulank'] }}</p></div>
        @break
    @case('lo-shu-grid')
        @php $g = $r['loshu']; @endphp
        <div class="card mt-6 p-5">
            <table class="mx-auto border-collapse text-center text-lg" aria-label="Lo Shu grid"><tbody>
                @foreach ($g['layout'] as $row)<tr>@foreach ($row as $d)<td class="size-20 border border-ink-200 {{ $g['counts'][$d] ? 'font-semibold' : 'text-ink-400' }}">{{ $g['counts'][$d] ? str_repeat((string) $d, $g['counts'][$d]) : '—' }}</td>@endforeach</tr>@endforeach
            </tbody></table>
            <p class="mt-4 text-sm">Missing numbers: {{ $g['missing'] ? implode(', ', $g['missing']) : 'none' }}.@if ($g['complete_planes']) Complete planes: {{ implode(', ', $g['complete_planes']) }}.@endif</p>
        </div>
        @break
    @case('personal-year')
        <div class="card mt-6 p-5"><p class="text-lg">Personal year {{ $r['year'] }}: <strong>{{ $r['personal_year'] }}</strong></p></div>
        @break
    @case('favourable-alphabets')
        <div class="card mt-6 p-5 text-sm"><p>Mulank {{ $r['mulank'] }} · friendly numbers: <strong>{{ implode(', ', $r['traits']['friendly']) }}</strong></p>
            <p class="mt-2">Letters with those values (Chaldean): <strong>{{ implode(' ', $r['letters']) }}</strong></p></div>
        @break
    @case('lucky-colour')
        <div class="card mt-6 p-5"><p>Colours associated with Mulank {{ $r['mulank'] }}: <strong>{{ implode(', ', $r['traits']['colours']) }}</strong>.</p></div>
        @break
    @case('unlucky-colour')
        <div class="card mt-6 p-5"><p>Colours traditionally used less with Mulank {{ $r['mulank'] }}: <strong>{{ implode(', ', $r['traits']['avoid_colours']) }}</strong>. Nothing harmful — simply less preferred in this tradition.</p></div>
        @break
    @case('lucky-dates')
    @case('unlucky-dates')
        @php $list = $r['tool'] === 'lucky-dates' ? $r['dates']['favourable'] : $r['dates']['less_favourable']; @endphp
        <div class="card mt-6 p-5"><p>{{ \Illuminate\Support\Carbon::parse($r['month'].'-01')->format('F Y') }}, Mulank {{ $r['mulank'] }} — {{ $r['tool'] === 'lucky-dates' ? 'favourable' : 'less favourable' }} dates:</p>
            <p class="mt-2 text-lg font-semibold">{{ $list ? implode(', ', $list) : 'None' }}</p>
            <p class="mt-2 text-xs text-ink-600">Based only on the day number. For important events use a proper Muhurat from the Panchang.</p></div>
        @break
    @case('vehicle-number')
        <div class="card mt-6 p-5"><p>{{ $r['plate'] }} reduces to <strong>{{ $r['vehicle']['number'] }}</strong>. With Mulank {{ $r['mulank'] }} this is traditionally <strong>{{ $r['vehicle']['verdict'] }}</strong>.</p></div>
        @break
    @case('love-calculator')
        <div class="card mt-6 p-5"><p>Name numbers {{ $r['love']['a'] }} and {{ $r['love']['b'] }} are traditionally <strong>{{ $r['love']['relation'] }}</strong> numbers.</p><p class="mt-2 text-sm text-ink-600">Just for fun — real relationships depend on people, not numbers.</p></div>
        @break
    @case('flames')
        <div class="card mt-6 p-5 text-center"><p class="text-3xl font-semibold">{{ $r['flames'] }}</p><p class="mt-2 text-sm text-ink-600">Just a game.</p></div>
        @break
@endswitch

@if ($c)
    <p class="mt-4 text-xs text-ink-600">Method: {{ $c['method']['ephemeris'] }}, {{ $c['method']['ayanamsha'] }} ayanamsha ({{ $c['method']['ayanamsha_value'] }}), {{ $c['method']['houses'] }}, {{ $c['method']['node'] }} node. Accurate birth time matters — 4 minutes can change the Lagna degree by about 1°.</p>
@endif
