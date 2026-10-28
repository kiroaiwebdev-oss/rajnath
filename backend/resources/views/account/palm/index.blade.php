<x-layouts.account title="Palmistry reports">
    @if ($readings->isEmpty())
        <div class="card p-8 text-center"><p class="text-ink-600">You have no palmistry reports yet.</p><a href="{{ route('services.show', 'palmistry-photo-report') }}" class="btn-primary mt-4">Get a palmistry report</a></div>
    @else
        <ul class="space-y-3">
            @foreach ($readings as $r)
                <li class="card flex flex-wrap items-center justify-between gap-3 p-4">
                    <div><p class="font-medium">Palmistry report · {{ substr($r->ref, -8) }}</p><p class="text-xs text-ink-600">Ordered {{ \Illuminate\Support\Carbon::parse($r->created_at)->tz(auth()->user()->display_timezone)->format('d M Y') }}</p></div>
                    <div class="flex items-center gap-3">
                        @include('account.palm._status', ['s' => $r->status])
                        <a href="{{ route('account.palm.show', $r->ref) }}" class="btn-outline">{{ in_array($r->status, ['awaiting_photos', 'needs_new_photos']) ? 'Upload photos' : 'Open' }}</a>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.account>
