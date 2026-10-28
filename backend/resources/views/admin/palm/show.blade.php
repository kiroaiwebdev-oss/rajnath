<x-layouts.admin :title="'Palmistry '.substr($r->ref, -8)">
    <div class="grid gap-6 xl:grid-cols-[22rem_1fr]">
        <section class="card h-fit p-5 text-sm">
            <p><strong>{{ $customer->name }}</strong> · {{ str_replace('_', ' ', $r->status) }}</p>
            <p class="mt-1 text-ink-600">Writes with: {{ $r->dominant_hand ?? '—' }} · Age: {{ $r->age ?? '—' }} · Gender: {{ $r->gender ?? '—' }} · Language: {{ $r->report_language === 'hi' ? 'Hindi' : 'English' }}</p>
            @if ($r->question)<p class="mt-3 rounded-xl bg-ivory-100 p-3"><strong>Question:</strong> {{ $r->question }}</p>@endif
            @if ($r->left_photo)
                <div class="mt-4 grid grid-cols-2 gap-2">@foreach (['left' => 'Left palm', 'right' => 'Right palm'] as $s => $l)
                    <a href="{{ route('palm.photo', [$r->ref, $s]) }}" target="_blank" rel="noopener"><img src="{{ route('palm.photo', [$r->ref, $s]) }}" alt="{{ $l }}" class="aspect-[3/4] w-full rounded-xl object-cover"><span class="text-xs">{{ $l }} (open full size)</span></a>
                @endforeach</div>
            @elseif ($r->photos_purged_at)<p class="mt-4 text-ink-600">Photos deleted under the retention policy.</p>@endif
            @if (in_array($r->status, ['submitted', 'in_review']))
                @if (! $r->assigned_to)<form method="POST" action="{{ route('admin.palm.claim', $r->ref) }}" class="mt-4">@csrf<button class="btn-outline w-full">Assign to me</button></form>@endif
                <form method="POST" action="{{ route('admin.palm.need', $r->ref) }}" class="mt-4 space-y-2">@csrf
                    <x-field name="note" label="Ask for new photos — tell the customer why" />
                    <button class="btn-outline w-full">Request new photos</button></form>
            @endif
        </section>
        <form method="POST" action="{{ route('admin.palm.report', $r->ref) }}" class="card min-w-0 space-y-4 p-5">
            @csrf <h2 class="text-lg">Report</h2>
            @foreach (\App\Domain\Palmistry\PalmReadings::SECTIONS as $k => $title)
                <div><label for="s-{{ $k }}" class="field-label">{{ $title }}</label>
                    <textarea id="s-{{ $k }}" name="sections[{{ $k }}]" rows="{{ in_array($k, ['overview', 'guidance']) ? 5 : 3 }}" class="input" @disabled($r->status === 'completed')>{{ old("sections.$k", $report[$k] ?? '') }}</textarea></div>
            @endforeach
            @error('overview')<p class="field-error">{{ $message }}</p>@enderror
            @if (in_array($r->status, ['submitted', 'in_review']))
                <div class="flex flex-col gap-3 sm:flex-row sm:justify-end"><button name="action" value="draft" class="btn-outline">Save draft</button><button name="action" value="complete" class="btn-primary">Publish to customer</button></div>
            @endif
        </form>
    </div>
</x-layouts.admin>
