<x-layouts.admin title="Rashifal">
    <div class="grid gap-6 xl:grid-cols-[1fr_26rem]">
        <section class="min-w-0">
            <div class="table-wrap" role="region" aria-label="Rashifal entries" tabindex="0"><table class="table">
                <thead><tr><th scope="col">Period</th><th scope="col">Sign</th><th scope="col">Language</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>@forelse ($rows as $h)<tr>
                    <td class="whitespace-nowrap">{{ ucfirst($h->period) }} · {{ \Illuminate\Support\Carbon::parse($h->starts_on)->format('d M Y') }}</td>
                    <td>{{ \App\Http\Controllers\Web\ContentController::SIGNS_HI[$h->sign] }} / {{ \Illuminate\Support\Str::before(\App\Domain\Astro\Vedic::SIGNS[$h->sign], ' ') }}</td><td>{{ $h->locale }}</td>
                    <td><span @class(['badge-success' => $h->status === 'published', 'badge-gold' => $h->status === 'draft', 'badge-danger' => $h->status === 'rejected'])>{{ $h->status }}</span> <span class="text-xs text-ink-600">v{{ $h->version }}</span></td>
                    <td>@if ($h->status === 'draft')@can('content.publish')<form method="POST" action="{{ route('admin.ops.horoscope.decide', $h->id) }}" class="flex gap-1">@csrf
                        <button name="decision" value="publish" class="btn-primary px-3">Publish</button><button name="decision" value="reject" class="btn-outline px-3">Reject</button></form>@endcan @endif</td></tr>
                    <tr><td colspan="5" class="text-xs text-ink-600">{{ \Illuminate\Support\Str::limit($h->body, 160) }}</td></tr>
                @empty<tr><td colspan="5" class="py-8 text-center text-ink-600">No entries yet.</td></tr>@endforelse</tbody>
            </table></div>
            <div class="mt-4">{{ $rows->links() }}</div>
        </section>
        <form method="POST" action="{{ route('admin.ops.horoscope') }}" class="card h-fit space-y-4 p-5">
            @csrf <h2 class="text-lg">Write Rashifal</h2>
            <div class="grid grid-cols-2 gap-3">
                <x-select name="period" label="Period" :options="['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly']" />
                <x-select name="locale" label="Language" :options="['hi' => 'हिन्दी', 'en' => 'English']" />
                <x-select name="sign" label="Rashi" :options="collect(\App\Http\Controllers\Web\ContentController::SIGNS_HI)->map(fn ($n, $i) => $n.' — '.\Illuminate\Support\Str::before(\App\Domain\Astro\Vedic::SIGNS[$i], ' '))->all()" />
                <x-field name="starts_on" label="Starts on" type="date" :value="now('Asia/Kolkata')->toDateString()" />
            </div>
            <div><label for="f-body" class="field-label">Text</label><textarea id="f-body" name="body" rows="7" class="input" required>{{ old('body') }}</textarea>@error('body')<p class="field-error">{{ $message }}</p>@enderror</div>
            <p class="text-xs text-ink-600">Weekly runs 7 days from the start date; monthly to month end; yearly to year end. A different person must publish. Only the period containing today is shown publicly.</p>
            <button class="btn-primary w-full">Save draft</button>
        </form>
    </div>
</x-layouts.admin>
