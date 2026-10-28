<x-layouts.admin :title="$title">
    <div class="grid gap-6 xl:grid-cols-[1fr_24rem]">
        <section class="card p-5 text-sm">
            <p><strong>{{ $customer->name }}</strong> · @include('account.puja._status', ['s' => $b->status]) · {{ ['online' => 'On their behalf (online)', 'temple' => 'At temple', 'home' => 'At home'][$b->location] ?? '—' }}</p>
            @if ($sankalp)
                <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div><dt class="text-ink-600">Sankalp names</dt><dd>@foreach ($sankalp['devotees'] as $d){{ $d['name'] }}{{ $d['relation'] ? ' ('.$d['relation'].')' : '' }}<br>@endforeach</dd></div>
                    <div><dt class="text-ink-600">Gotra</dt><dd>{{ $sankalp['gotra'] ?? '—' }}</dd></div>
                    <div><dt class="text-ink-600">Phone</dt><dd>{{ $sankalp['phone'] }}</dd></div>
                    <div><dt class="text-ink-600">Preferred date</dt><dd>{{ $b->preferred_date ? \Illuminate\Support\Carbon::parse($b->preferred_date)->format('d M Y') : '—' }}</dd></div>
                    @if ($sankalp['address'])<div class="sm:col-span-2"><dt class="text-ink-600">Address</dt><dd>{{ $sankalp['address'] }}</dd></div>@endif
                    @if ($sankalp['wish'])<div class="sm:col-span-2"><dt class="text-ink-600">Wish</dt><dd>{{ $sankalp['wish'] }}</dd></div>@endif
                </dl>
            @else
                <p class="mt-4 text-ink-600">The customer has not given sankalp details yet.</p>
            @endif
            @if ($b->scheduled_at)<p class="mt-4">Scheduled: <strong>{{ \Illuminate\Support\Carbon::parse($b->scheduled_at, 'UTC')->tz('Asia/Kolkata')->format('d M Y, g:i A') }} IST</strong>@if ($b->live_link) · <a href="{{ $b->live_link }}" rel="noopener">live link</a>@endif</p>@endif
            @if ($evidence)<div class="mt-4 grid grid-cols-3 gap-2">@foreach ($evidence['photos'] as $i => $p)<img src="{{ route('puja.photo', [$b->ref, $i]) }}" alt="Proof photo {{ $i + 1 }}" class="aspect-square w-full rounded-xl object-cover">@endforeach</div>@endif
        </section>
        <div class="space-y-4">
            @if (in_array($b->status, ['requested', 'scheduled']))
                <form method="POST" action="{{ route('admin.ops.puja.action', $b->ref) }}" class="card space-y-3 p-5">@csrf <input type="hidden" name="action" value="schedule">
                    <h2 class="text-base">{{ $b->status === 'scheduled' ? 'Change schedule' : 'Assign & schedule' }}</h2>
                    @if ($suppliers->isEmpty())<p class="text-sm text-danger-700">No authorised priest/temple is linked to this puja. Add one in Priests &amp; temples.</p>
                    @else
                        <x-select name="supplier" label="Priest / temple" :value="$b->supplier_id" :options="$suppliers->mapWithKeys(fn ($s) => [$s->id => $s->name.' ('.$s->city.')'])->all()" />
                        <x-field name="scheduled_at" label="Date & time (IST)" type="datetime-local" :value="$b->scheduled_at ? \Illuminate\Support\Carbon::parse($b->scheduled_at, 'UTC')->tz('Asia/Kolkata')->format('Y-m-d\TH:i') : null" required />
                        <x-field name="live_link" label="Live stream link (optional, https)" :value="$b->live_link" />
                        <x-field name="note" label="Note to customer (optional)" :value="$b->customer_note" />
                        <button class="btn-primary w-full">Save schedule & notify</button>
                    @endif
                </form>
            @endif
            @if ($b->status === 'scheduled')
                <form method="POST" action="{{ route('admin.ops.puja.action', $b->ref) }}" enctype="multipart/form-data" class="card space-y-3 p-5">@csrf <input type="hidden" name="action" value="performed">
                    <h2 class="text-base">Mark performed</h2>
                    <div><label for="f-ph" class="field-label">Photos from the puja (required, up to 10)</label><input id="f-ph" name="photos[]" type="file" multiple accept="image/jpeg,image/png,image/webp" class="input" required>@error('photos')<p class="field-error">{{ $message }}</p>@enderror</div>
                    <x-field name="video_url" label="Video link (optional, https)" />
                    <button class="btn-primary w-full">Upload proof</button>
                </form>
            @endif
            @if ($b->status === 'performed')
                <form method="POST" action="{{ route('admin.ops.puja.action', $b->ref) }}" class="card p-5">@csrf <input type="hidden" name="action" value="complete"><p class="text-sm">Check the photos, then complete. The customer is notified.</p><button class="btn-primary mt-3 w-full">Verify & complete</button></form>
            @endif
            @if (! in_array($b->status, ['completed', 'cancelled']))
                <form method="POST" action="{{ route('admin.ops.puja.action', $b->ref) }}" class="card space-y-3 p-5">@csrf <input type="hidden" name="action" value="cancel">
                    <x-field name="reason" label="Cancel — reason shown to customer" /><button class="btn-danger w-full">Cancel booking</button>
                    <p class="text-xs text-ink-600">Then refund from Finance › order.</p></form>
            @endif
        </div>
    </div>
</x-layouts.admin>
