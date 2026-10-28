<x-layouts.account title="Bookings">
    @if ($bookings->isEmpty())
        <div class="card p-8 text-center"><p class="text-ink-600">No consultations booked yet.</p><a href="{{ route('services.show', 'consultation-with-acharya-ji') }}" class="btn-primary mt-4">Book a consultation</a></div>
    @else
        <ul class="space-y-3">
            @foreach ($bookings as $b)
                @php $start = \Illuminate\Support\Carbon::parse($b->start_utc, 'UTC')->tz('Asia/Kolkata'); $future = $start->isFuture(); $changeable = $b->status === 'confirmed' && $start->copy()->subHours(config('booking.change_hours'))->isFuture(); @endphp
                <li class="card p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><p class="font-medium">{{ $b->title }} — {{ $b->variant }}</p>
                            <p class="text-sm text-ink-600">{{ $start->format('l, d M Y, g:i A') }} IST · {{ \App\Models\OfferingVariant::MODES[$b->mode] ?? $b->mode }}</p>
                            @if ($b->meeting_link && $future)<p class="mt-1 text-sm"><a href="{{ $b->meeting_link }}" rel="noopener">Join link</a></p>@endif</div>
                        <span @class(['badge-success' => $b->status === 'confirmed', 'badge-ink' => $b->status !== 'confirmed'])>{{ str_replace('_', ' ', ucfirst($b->status)) }}</span>
                    </div>
                    @if ($changeable)
                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($b->reschedules < config('booking.max_reschedules'))<a href="{{ route('account.bookings.reschedule', $b->ref) }}" class="btn-outline">Change time</a>@endif
                            <form method="POST" action="{{ route('account.bookings.cancel', $b->ref) }}">@csrf<button class="btn-ghost px-3 text-danger-700">Cancel and refund to wallet</button></form>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.account>
