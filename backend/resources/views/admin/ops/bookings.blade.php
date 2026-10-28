<x-layouts.admin title="Bookings & working hours">
    @php $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']; @endphp
    <div class="grid gap-6 xl:grid-cols-[1fr_24rem]">
        <section class="min-w-0" aria-labelledby="up-h">
            <h2 id="up-h" class="text-base">Upcoming confirmed consultations</h2>
            <div class="table-wrap mt-3" role="region" aria-label="Upcoming bookings" tabindex="0"><table class="table">
                <thead><tr><th scope="col">When (IST)</th><th scope="col">Customer</th><th scope="col">Option</th><th scope="col">Link / outcome</th></tr></thead>
                <tbody>@forelse ($upcoming as $b)<tr>
                    <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($b->start_utc, 'UTC')->tz('Asia/Kolkata')->format('D d M, g:i A') }}</td><td>{{ $b->customer }}@if ($b->topic)<br><span class="text-xs text-ink-600">{{ \Illuminate\Support\Str::limit($b->topic, 80) }}</span>@endif</td><td>{{ $b->variant }}</td>
                    <td>@can('bookings.manage')<form method="POST" action="{{ route('admin.ops.booking', $b->ref) }}" class="flex flex-wrap gap-2">@csrf @method('PUT')
                        <label class="sr-only" for="ml-{{ $b->id }}">Meeting link</label><input id="ml-{{ $b->id }}" name="meeting_link" value="{{ $b->meeting_link }}" placeholder="https://meet…" class="input min-h-9 w-48 py-1.5 text-sm">
                        <button class="btn-outline px-3">Save link</button>
                        <button name="status" value="completed" class="btn-ghost px-3">Done</button><button name="status" value="no_show" class="btn-ghost px-3">No-show</button></form>@endcan</td></tr>
                @empty<tr><td colspan="4" class="py-8 text-center text-ink-600">No upcoming bookings.</td></tr>@endforelse</tbody>
            </table></div>
        </section>
        <section class="space-y-6">
            <div class="card p-5"><h2 class="text-base">Working hours (IST)</h2>
                <ul class="mt-3 space-y-2 text-sm">@forelse ($rules as $r)<li class="flex items-center justify-between gap-2"><span>{{ $days[$r->weekday] }} {{ substr($r->start_local, 0, 5) }}–{{ substr($r->end_local, 0, 5) }} · {{ $r->slot_minutes }} min</span>
                    @can('bookings.manage')<form method="POST" action="{{ route('admin.ops.rule.delete', $r->id) }}">@csrf @method('DELETE')<button class="btn-ghost px-2 text-danger-700">Remove</button></form>@endcan</li>
                @empty<li class="text-ink-600">No hours set — customers cannot book online until you add hours.</li>@endforelse</ul>
                @can('bookings.manage')<form method="POST" action="{{ route('admin.ops.rule') }}" class="mt-4 grid grid-cols-2 gap-2">@csrf
                    <x-select name="weekday" label="Day" :options="$days" /><x-select name="slot_minutes" label="Slot" :options="[30 => '30 min', 15 => '15 min', 45 => '45 min', 60 => '60 min']" />
                    <x-field name="start_local" label="From" type="time" value="08:00" /><x-field name="end_local" label="To" type="time" value="12:00" />
                    <button class="btn-primary col-span-2">Add hours</button></form>@endcan
            </div>
            <div class="card p-5"><h2 class="text-base">Leave days</h2>
                <ul class="mt-2 text-sm">@forelse ($leave as $l)<li>{{ \Illuminate\Support\Carbon::parse($l->date)->format('D, d M Y') }} {{ $l->reason }}</li>@empty<li class="text-ink-600">None.</li>@endforelse</ul>
                @can('bookings.manage')<form method="POST" action="{{ route('admin.ops.leave') }}" class="mt-3 grid grid-cols-2 gap-2">@csrf<x-field name="date" label="Date" type="date" /><x-field name="reason" label="Reason" /><button class="btn-outline col-span-2">Add leave day</button></form>@endcan
            </div>
        </section>
    </div>
</x-layouts.admin>
