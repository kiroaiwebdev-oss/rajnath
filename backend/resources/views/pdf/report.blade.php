{{-- Report PDF. $title, $subjects, $chart, $defs, $sections, $completedAt, $brand, $lang, $kind (report|palm) --}}
@extends('pdf.layout', ['headRight' => e($completedAt)])
@section('content')
    <h1>{{ $title }}</h1>
    @php $hi = ($lang ?? 'en') === 'hi'; @endphp
    <p class="muted">@if ($subjects){{ $hi ? '' : 'For ' }}{{ collect($subjects)->pluck('name')->implode(' & ') }}{{ $hi ? ' के लिए' : '' }} · @endif{{ $hi ? 'आचार्य डॉ. राजनाथ झा की टीम द्वारा तैयार' : "Prepared by Acharya Dr. Rajnath Jha's team" }}</p>
    @if ($chart)
        <div class="charts">
            @foreach ($chart['charts'] as $i => $c)
                <div>
                    @include('public.tools._chart', ['houses' => $c['houses'], 'label' => ($subjects[$i]['name'] ?? 'Chart').' — Rashi (D1)'])
                    <p class="small" style="text-align:center">{{ $subjects[$i]['name'] ?? '' }} · Lagna {{ $c['lagna']['sign_name'] }} · Moon {{ $c['moon_sign']['name'] }} · {{ $c['nakshatra']['name'] }} pada {{ $c['nakshatra']['pada'] }}</p>
                </div>
            @endforeach
        </div>
        @if (isset($chart['matching']))<p style="text-align:center;font-weight:700">Guna milan: {{ $chart['matching']['total'] }} / 36</p>@endif
    @endif
    @foreach ($defs as $k => $h)
        @if (! empty($sections[$k]))
            <section><h2>{{ $h }}</h2>@foreach (preg_split('/\R{2,}/', $sections[$k]) as $para)<p>{{ $para }}</p>@endforeach</section>
        @endif
    @endforeach
    <p class="note">@if ($hi){{ $kind === 'palm' ? 'हस्तरेखा एक पारंपरिक व्याख्यात्मक विद्या है। यह चिकित्सा, कानूनी या वित्तीय आकलन नहीं है।' : 'ज्योतिषीय मार्गदर्शन पारंपरिक और व्याख्यात्मक है। यह चिकित्सा, कानूनी या वित्तीय सलाह का विकल्प नहीं है। गणना: स्विस एफ़ेमेरिस, लाहिरी अयनांश।' }}@else{{ $kind === 'palm' ? 'Palmistry is a traditional interpretive practice. It is not a medical, legal or financial assessment.' : 'Astrological guidance is traditional and interpretive. It does not replace medical, legal or financial advice. Calculations: Swiss Ephemeris, Lahiri ayanamsha.' }}@endif</p>
@endsection
