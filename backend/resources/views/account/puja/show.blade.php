<x-layouts.account :title="$title">
    <div class="flex flex-wrap items-center gap-3">@include('account.puja._status', ['s' => $b->status])<span class="text-sm text-ink-600">Ref {{ substr($b->ref, -8) }}</span></div>
    @if ($b->scheduled_at)
        <div class="card mt-5 p-5"><p class="font-medium">Scheduled for {{ \Illuminate\Support\Carbon::parse($b->scheduled_at, 'UTC')->tz('Asia/Kolkata')->format('l, d M Y, g:i A') }} IST</p>
            @if ($b->live_link && $b->status === 'scheduled')<a href="{{ $b->live_link }}" rel="noopener" class="btn-primary mt-3">Join live</a>@endif
            @if ($b->customer_note)<p class="mt-2 text-sm">{{ $b->customer_note }}</p>@endif</div>
    @endif

    @if (in_array($b->status, ['awaiting_details', 'requested']))
        <form method="POST" action="{{ route('account.puja.details', $b->ref) }}" class="card mt-5 max-w-3xl space-y-5 p-5 sm:p-6" novalidate>
            @csrf
            <h2 class="text-lg">Sankalp details</h2>
            <p class="text-sm text-ink-600">The priest takes the sankalp in these names. Details are kept private.</p>
            <fieldset class="space-y-3"><legend class="field-label">Names for the sankalp (up to 8)</legend>
                @php $devs = old('devotees', $sankalp['devotees'] ?? [['name' => auth()->user()->name, 'relation' => 'Self']]); $devs = array_pad($devs, max(3, count($devs)), ['name' => '', 'relation' => '']); @endphp
                @foreach (array_slice($devs, 0, 8) as $i => $dv)
                    <div class="grid grid-cols-[1fr_9rem] gap-2">
                        <div><label for="dn{{ $i }}" class="sr-only">Name {{ $i + 1 }}</label><input id="dn{{ $i }}" name="devotees[{{ $i }}][name]" value="{{ $dv['name'] }}" class="input" placeholder="Full name"></div>
                        <div><label for="dr{{ $i }}" class="sr-only">Relation {{ $i + 1 }}</label><input id="dr{{ $i }}" name="devotees[{{ $i }}][relation]" value="{{ $dv['relation'] }}" class="input" placeholder="Relation"></div>
                    </div>
                @endforeach
                @error('devotees')<p class="field-error">{{ $message }}</p>@enderror @error('devotees.0.name')<p class="field-error">{{ $message }}</p>@enderror
            </fieldset>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="gotra" label="Gotra (optional)" :value="$sankalp['gotra'] ?? null" />
                <x-field name="phone" label="Mobile for coordination" type="tel" :value="$sankalp['phone'] ?? auth()->user()->phone_e164" required />
                <x-select name="location" label="Where" :value="$b->location ?? 'online'" :options="['online' => 'Performed on my behalf (watch online / photos)', 'temple' => 'At a temple, I will attend', 'home' => 'At my home (Patna area)']" />
                <x-field name="preferred_date" label="Preferred date" type="date" :value="$b->preferred_date" :min="now('Asia/Kolkata')->format('Y-m-d')" />
            </div>
            <div><label for="f-address" class="field-label">Address (for puja at home)</label><textarea id="f-address" name="address" rows="2" class="input">{{ old('address', $sankalp['address'] ?? '') }}</textarea>@error('address')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div><label for="f-wish" class="field-label">Your wish / purpose (optional)</label><textarea id="f-wish" name="wish" rows="2" maxlength="500" class="input">{{ old('wish', $sankalp['wish'] ?? '') }}</textarea></div>
            <p class="rounded-xl bg-ivory-100 p-3 text-xs">Puja is a spiritual practice performed with care; it does not guarantee any result and does not replace medical, legal or other professional help.</p>
            <button class="btn-primary">{{ $b->status === 'requested' ? 'Update details' : 'Submit details' }}</button>
        </form>
    @elseif ($sankalp)
        <div class="card mt-5 p-5 text-sm"><h2 class="text-base">Sankalp</h2><p class="mt-2">{{ collect($sankalp['devotees'])->pluck('name')->implode(', ') }}@if ($sankalp['gotra']) · Gotra {{ $sankalp['gotra'] }}@endif</p></div>
    @endif

    @if ($evidence && in_array($b->status, ['performed', 'completed']))
        <section class="card mt-5 p-5" aria-labelledby="ev-h"><h2 id="ev-h" class="text-lg">Photos from your puja</h2>
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">@foreach ($evidence['photos'] as $i => $p)<a href="{{ route('puja.photo', [$b->ref, $i]) }}" target="_blank" rel="noopener"><img src="{{ route('puja.photo', [$b->ref, $i]) }}" alt="Puja photo {{ $i + 1 }}" class="aspect-square w-full rounded-xl object-cover"></a>@endforeach</div>
            @if (! empty($evidence['video_url']))<a href="{{ $evidence['video_url'] }}" rel="noopener" class="btn-outline mt-4">Watch video</a>@endif
        </section>
    @endif
</x-layouts.account>
