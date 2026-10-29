<x-layouts.account :title="$title">
    @if ($j->status === 'awaiting_details')
        <form method="POST" action="{{ route('account.reports.details', $j->ref) }}" class="card max-w-2xl space-y-5 p-5 sm:p-6">
            @csrf
            <h2 class="text-lg">Whose report is this?</h2>
            @if ($profiles->isEmpty())
                <p class="text-sm">First <a href="{{ route('account.birth-profiles.create') }}">add a birth profile</a> with date, time and place of birth.</p>
            @else
                <x-select name="profile_a" :label="$two ? 'First person' : 'Birth profile'" :options="$profiles->mapWithKeys(fn ($p) => [$p->ref => $p->name.' — '.$p->birth_date->format('d M Y')])->all()" />
                @if ($two)<x-select name="profile_b" label="Second person" :options="$profiles->mapWithKeys(fn ($p) => [$p->ref => $p->name.' — '.$p->birth_date->format('d M Y')])->all()" :value="$profiles->skip(1)->first()?->ref" />@endif
                <x-select name="language" label="Report language" :options="['en' => 'English', 'hi' => 'Hindi']" />
                @php $ask = str_starts_with($j->offering_slug, 'ask-'); @endphp
                <div><label for="f-q" class="field-label">{{ $ask ? 'Your question (for two or three questions, write each on a new line)' : 'Anything specific you want covered? (optional)' }}</label><textarea id="f-q" name="question" rows="{{ $ask ? 6 : 3 }}" maxlength="2000" class="input" @if ($ask) required @endif></textarea></div>
                <button class="btn-primary">Submit</button>
                <p class="text-xs text-ink-600"><a href="{{ route('account.birth-profiles.create') }}">Add another birth profile</a></p>
            @endif
        </form>
    @elseif ($j->status === 'in_preparation')
        <div class="card p-6"><p>Your chart has been calculated and Acharya Ji's team is preparing your report. We will email you when it is ready.</p></div>
    @else
        <article class="card p-6 print:border-0 print:shadow-none" id="report">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><p class="eyebrow">{{ $site('brand.name') }}</p><h2 class="mt-1 text-2xl">{{ $title }}</h2>
                    <p class="text-sm text-ink-600">For {{ collect($subjects)->pluck('name')->implode(' & ') }} · Prepared by Acharya Dr. Rajnath Jha's team · {{ \Illuminate\Support\Carbon::parse($j->completed_at)->tz('Asia/Kolkata')->format('d M Y') }}</p></div>
                <div class="flex gap-2 print:hidden"><a href="{{ route('account.reports.pdf', $j->ref) }}" class="btn-primary">Download PDF</a><button type="button" class="btn-outline" x-data="printer" @click="print">Print</button></div>
            </div>
            @if ($chart)
                <div class="mt-6 grid gap-6 md:grid-cols-2">
                    @foreach ($chart['charts'] as $i => $c)
                        <div>@include('public.tools._chart', ['houses' => $c['houses'], 'label' => ($subjects[$i]['name'] ?? 'Chart').' — Rashi (D1)'])
                            <p class="mt-2 text-center text-sm">Lagna {{ $c['lagna']['sign_name'] }} · Moon {{ $c['moon_sign']['name'] }} · {{ $c['nakshatra']['name'] }} pada {{ $c['nakshatra']['pada'] }}</p></div>
                    @endforeach
                </div>
                @if (isset($chart['matching']))<p class="mt-4 text-center text-lg font-semibold">Guna milan: {{ $chart['matching']['total'] }} / 36</p>@endif
            @endif
            @foreach ($defs as $k => $h)
                @if (! empty($sections[$k]))<section class="mt-8 break-inside-avoid"><h3 class="text-lg">{{ $h }}</h3><div class="mt-2 space-y-3 leading-7">@foreach (preg_split('/\R{2,}/', $sections[$k]) as $p)<p>{{ $p }}</p>@endforeach</div></section>@endif
            @endforeach
            <p class="mt-10 rounded-xl bg-ivory-100 p-3 text-xs">Astrological guidance is traditional and interpretive. It does not replace medical, legal or financial advice. Calculations: Swiss Ephemeris, Lahiri ayanamsha.</p>
        </article>
    @endif
</x-layouts.account>
