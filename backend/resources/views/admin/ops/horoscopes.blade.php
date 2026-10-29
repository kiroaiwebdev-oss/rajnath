@php $T = \App\Domain\Content\HoroscopeTypes::class; @endphp
<x-layouts.admin title="Rashifal">
    <div class="grid gap-6 xl:grid-cols-[1fr_26rem]">
        <section class="min-w-0">
            <div class="table-wrap" role="region" aria-label="Rashifal entries" tabindex="0"><table class="table">
                <thead><tr><th scope="col">Type</th><th scope="col">Period</th><th scope="col">Subject</th><th scope="col">Language</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>@forelse ($rows as $h)<tr>
                    <td class="whitespace-nowrap">{{ $T::KINDS[$h->kind][1] ?? $h->kind }}@if ($h->topic !== 'general') · {{ $T::TOPICS[$h->topic][1] ?? \App\Domain\Astro\Vedic::PLANET_NAMES[$h->topic] ?? $h->topic }}@endif</td>
                    <td class="whitespace-nowrap">{{ $T::PERIODS[$h->period][1] ?? ucfirst($h->period) }} · {{ \Illuminate\Support\Carbon::parse($h->starts_on)->format('d M Y') }}</td>
                    <td>{{ $T::subjects($h->kind, 'hi')[$h->sign] ?? $h->sign }} / {{ \Illuminate\Support\Str::before($T::subjects($h->kind)[$h->sign] ?? '', ' (') }}</td><td>{{ $h->locale }}</td>
                    <td><span @class(['badge-success' => $h->status === 'published', 'badge-gold' => $h->status === 'draft', 'badge-danger' => $h->status === 'rejected'])>{{ $h->status }}</span> <span class="text-xs text-ink-600">v{{ $h->version }}</span></td>
                    <td>@if ($h->status === 'draft')@can('content.publish')<form method="POST" action="{{ route('admin.ops.horoscope.decide', $h->id) }}" class="flex gap-1">@csrf
                        <button name="decision" value="publish" class="btn-primary px-3">Publish</button><button name="decision" value="reject" class="btn-outline px-3">Reject</button></form>@endcan @endif</td></tr>
                    <tr><td colspan="6" class="text-xs text-ink-600">{{ \Illuminate\Support\Str::limit($h->body, 160) }}</td></tr>
                @empty<tr><td colspan="6" class="py-8 text-center text-ink-600">No entries yet.</td></tr>@endforelse</tbody>
            </table></div>
            <div class="mt-4">{{ $rows->links() }}</div>
        </section>
        <form method="POST" action="{{ route('admin.ops.horoscope') }}" class="card h-fit space-y-4 p-5">
            @csrf <h2 class="text-lg">Write Rashifal / horoscope</h2>
            <div>
                <label for="f-subject" class="field-label">For</label>
                <select id="f-subject" name="subject" class="input" required>
                    @foreach ($T::KINDS as $k => $label)
                        <optgroup label="{{ $label[1] }}">
                            @foreach ($T::subjects($k, 'hi') as $i => $n)<option value="{{ $k }}:{{ $i }}" @selected(old('subject') === "$k:$i")>{{ $n }} — {{ \Illuminate\Support\Str::before($T::subjects($k)[$i], ' (') }}</option>@endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('sign')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="f-topic" class="field-label">Topic / planet</label>
                    <select id="f-topic" name="topic" class="input">
                        <optgroup label="Rashifal topic">@foreach ($T::TOPICS as $k => $t)<option value="{{ $k }}" @selected(old('topic') === $k)>{{ $t[1] }}</option>@endforeach</optgroup>
                        <optgroup label="Transit planet (gochar)">@foreach (\App\Domain\Astro\Gochar::PLANETS as $pl)<option value="{{ $pl }}" @selected(old('topic') === $pl)>{{ \App\Domain\Astro\Vedic::PLANET_NAMES[$pl] }}</option>@endforeach</optgroup>
                    </select>
                    @error('topic')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <x-select name="period" label="Period" :options="collect($T::PERIODS)->map(fn ($p) => $p[1])->all()" />
                <x-select name="locale" label="Language" :options="['hi' => 'हिन्दी', 'en' => 'English']" />
                <x-field name="starts_on" label="Starts on" type="date" :value="now('Asia/Kolkata')->toDateString()" />
            </div>
            <div><label for="f-body" class="field-label">Text</label><textarea id="f-body" name="body" rows="7" class="input" required>{{ old('body') }}</textarea>@error('body')<p class="field-error">{{ $message }}</p>@enderror</div>
            <p class="text-xs text-ink-600">Weekly runs 7 days from the start date; monthly to month end; half-yearly 6 months; yearly to year end (start yearly on 1 January, e.g. 2027-01-01 for Varshik Rashifal 2027). Love, career, finance, health and education apply to Rashifal only. For transit effects choose "Transit effects" with the Moon sign, and the planet as topic; they appear on that planet's Gochar page. A different person must publish. Only the period containing today is shown publicly, except yearly and half-yearly, which can be read by year.</p>
            <button class="btn-primary w-full">Save draft</button>
        </form>
    </div>
</x-layouts.admin>
