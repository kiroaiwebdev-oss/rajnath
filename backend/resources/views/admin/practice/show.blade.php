<x-layouts.admin title="My practice">
    @php $M = fn ($v) => \App\Support\Money::format((int) $v); $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']; @endphp
    <p class="text-sm text-ink-600">Profile: <strong>{{ $p->status }}</strong>
        @if ($p->status === 'draft') — complete it and send it for review.@elseif ($p->status === 'submitted') — waiting for review.@endif
        · Your share: {{ $share !== null ? ($share / 100).'%' : 'not set yet' }} · Payout to: {{ $payout }}</p>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <form method="POST" action="{{ route('admin.practice.profile') }}" enctype="multipart/form-data" class="card min-w-0 space-y-4 p-5" aria-labelledby="mp-h">
            @csrf @method('PUT')
            <h2 id="mp-h" class="text-lg">Public profile</h2>
            <x-field name="display_name" label="Name shown to customers" :value="$p->display_name" />
            <div><label for="f-bio" class="field-label">About you (60–2000 characters)</label><textarea id="f-bio" name="bio" rows="5" maxlength="2000" class="input">{{ old('bio', $p->bio) }}</textarea>@error('bio')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-field name="languages" label="Languages" :value="$p->languages" hint="Comma separated" />
                <x-field name="specialties" label="Specialties" :value="$p->specialties" hint="Comma separated" />
                <x-field name="experience_years" label="Years of practice" type="number" :value="$p->experience_years" />
            </div>
            <div><label for="f-photo" class="field-label">Photo (JPG/PNG, optional)</label><input id="f-photo" type="file" name="photo" accept="image/jpeg,image/png" class="input">@error('photo')<p class="field-error">{{ $message }}</p>@enderror</div>
            <p class="text-xs text-ink-600">Write only true facts. Changes to an approved profile are reviewed again before customers see them.</p>
            <div class="flex flex-wrap gap-2"><button class="btn-outline">Save</button><button name="submit" value="1" class="btn-primary">Save and send for review</button></div>
        </form>

        <div class="space-y-6">
            <form method="POST" action="{{ route('admin.practice.payout') }}" class="card space-y-4 p-5" aria-labelledby="pd-h">
                @csrf @method('PUT')
                <h2 id="pd-h" class="text-lg">Where we send your payouts</h2>
                <x-select name="method" label="Method" :options="['upi' => 'UPI', 'bank' => 'Bank account']" />
                <x-field name="upi" label="UPI ID (for UPI)" />
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field name="account_name" label="Account holder" />
                    <x-field name="account_number" label="Account number" />
                    <x-field name="ifsc" label="IFSC" />
                </div>
                <button class="btn-outline">Save payout details</button>
            </form>

            <section class="card p-5" aria-labelledby="hr-h">
                <h2 id="hr-h" class="text-lg">Working hours (IST)</h2>
                <ul class="mt-2 divide-y divide-ink-200/60 text-sm">
                    @forelse ($hours as $h)
                        <li class="flex items-center justify-between py-2">{{ $days[$h->weekday] }} {{ substr($h->start_local, 0, 5) }}–{{ substr($h->end_local, 0, 5) }} · {{ $h->slot_minutes }} min
                            <form method="POST" action="{{ route('admin.practice.hours.delete', $h->id) }}">@csrf @method('DELETE')<button class="btn-ghost">Remove</button></form></li>
                    @empty
                        <li class="py-2 text-ink-600">No hours yet — customers cannot book you.</li>
                    @endforelse
                </ul>
                <form method="POST" action="{{ route('admin.practice.hours') }}" class="mt-3 grid gap-3 sm:grid-cols-2 2xl:grid-cols-5 sm:items-end">
                    @csrf
                    <x-select name="weekday" label="Day" :options="$days" />
                    <x-field name="start_local" label="From" type="time" value="10:00" />
                    <x-field name="end_local" label="To" type="time" value="13:00" />
                    <x-select name="slot_minutes" label="Slot" :options="[15 => '15', 30 => '30', 45 => '45', 60 => '60']" value="30" />
                    <button class="btn-outline">Add</button>
                </form>
                <form method="POST" action="{{ route('admin.practice.leave') }}" class="mt-4 flex flex-wrap items-end gap-3 border-t border-ink-200/60 pt-4">
                    @csrf
                    <x-field name="date" label="Leave day" type="date" />
                    <x-field name="reason" label="Reason (optional)" />
                    <button class="btn-outline">Add leave</button>
                </form>
                @if ($leave->isNotEmpty())<p class="mt-2 text-xs text-ink-600">Leave: {{ $leave->pluck('date')->map(fn ($d) => substr($d, 0, 10))->implode(', ') }}</p>@endif
            </section>
        </div>

        <section class="card min-w-0 p-5 xl:col-span-2" aria-labelledby="bk-h">
            <h2 id="bk-h" class="text-lg">My consultations</h2>
            @error('status')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            @error('provider')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            <ul class="mt-3 divide-y divide-ink-200/60 text-sm">
                @forelse ($bookings as $b)
                    <li class="grid gap-2 py-3 md:grid-cols-[1fr_auto] md:items-center">
                        <div><strong>{{ \App\Domain\Booking\Slots::when($b) }}</strong> · {{ $b->customer }} · {{ $b->mode }} · {{ $b->status }}
                            @if ($b->topic)<br><span class="text-ink-600">Topic: {{ $b->topic }}</span>@endif</div>
                        @if ($b->status === 'confirmed')
                            <form method="POST" action="{{ route('admin.practice.booking', $b->ref) }}" class="flex flex-wrap items-end gap-2">@csrf @method('PUT')
                                <label class="sr-only" for="ml-{{ $b->id }}">Meeting link</label>
                                <input id="ml-{{ $b->id }}" name="meeting_link" class="input w-56" placeholder="https://… call link" value="{{ $b->meeting_link }}">
                                <button class="btn-outline">Save link</button>
                                <button name="provider" value="livekit" class="btn-ghost">In-app call</button><button name="provider" value="zoom" class="btn-ghost">Zoom</button><button name="provider" value="google_meet" class="btn-ghost">Meet</button>
                                @if ($b->meeting_provider === 'livekit')<a class="btn-primary" href="{{ route('call.join', $b->ref) }}">Join call</a>@endif
                                <button name="status" value="completed" class="btn-primary">Completed</button>
                                <button name="status" value="no_show" class="btn-ghost">No-show</button></form>
                        @endif
                    </li>
                @empty
                    <li class="py-2 text-ink-600">No consultations yet.</li>
                @endforelse
            </ul>
        </section>

        <section class="card min-w-0 p-5 xl:col-span-2" aria-labelledby="ea-h">
            <h2 id="ea-h" class="text-lg">Earnings</h2>
            <p class="text-sm">Pending (7-day refund window): {{ $M($balances['pending']) }} · Available: {{ $M($balances['available']) }} · In payout: {{ $M($balances['in_payout']) }} · Paid: {{ $M($balances['paid']) }}</p>
            <ul class="mt-3 text-sm">@foreach ($payoutRows as $po)<li>{{ $M($po->amount_minor) }} · {{ $po->status }}@if ($po->utr) · UTR {{ $po->utr }}@endif · {{ substr($po->created_at, 0, 10) }}</li>@endforeach</ul>
            <a class="btn-outline mt-3" href="{{ route('admin.practice.statement') }}">Download statement (CSV)</a>
        </section>
    </div>
</x-layouts.admin>
