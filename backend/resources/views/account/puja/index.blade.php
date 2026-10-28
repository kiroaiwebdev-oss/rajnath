<x-layouts.account title="Puja bookings">
    @if ($rows->isEmpty())
        <div class="card p-8 text-center"><p class="text-ink-600">No puja booked yet.</p><a href="{{ route('services.index', ['family' => 'puja']) }}" class="btn-primary mt-4">Book a puja</a></div>
    @else
        <ul class="space-y-3">
            @foreach ($rows as $r)
                <li class="card flex flex-wrap items-center justify-between gap-3 p-4">
                    <div><p class="font-medium">{{ $r->title }}</p><p class="text-xs text-ink-600">Booked {{ \Illuminate\Support\Carbon::parse($r->created_at)->tz('Asia/Kolkata')->format('d M Y') }}@if ($r->scheduled_at) · Scheduled {{ \Illuminate\Support\Carbon::parse($r->scheduled_at, 'UTC')->tz('Asia/Kolkata')->format('d M Y, g:i A') }}@endif</p></div>
                    <div class="flex items-center gap-3">@include('account.puja._status', ['s' => $r->status])<a href="{{ route('account.puja.show', $r->ref) }}" class="btn-outline">{{ $r->status === 'awaiting_details' ? 'Add sankalp details' : 'Open' }}</a></div>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.account>
