<x-layouts.account title="Birth profiles">
    @if (session('tz_note'))
        <div role="alert" class="card mb-5 border-gold-200 bg-gold-50 p-4 text-sm">{{ session('tz_note') }}</div>
    @endif
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-ink-600">Save birth details for yourself and your family. They are private to your account.</p>
        <a href="{{ route('account.birth-profiles.create') }}" class="btn-primary">Add birth profile</a>
    </div>
    @if ($profiles->isEmpty())
        <div class="card mt-5 p-8 text-center"><h2 class="text-base">No birth profiles yet</h2><p class="mt-1 text-sm text-ink-600">Add yours first — it will be used by default.</p></div>
    @else
        <ul class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($profiles as $p)
                <li class="card p-5">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="text-base">{{ $p->name }}</h2>
                        @if ($p->is_primary)<span class="badge-gold">Primary</span>@endif
                    </div>
                    <dl class="mt-3 space-y-1 text-sm text-ink-700">
                        <div><dt class="sr-only">Relation</dt><dd>{{ \App\Models\BirthProfile::LABELS[$p->label] }}</dd></div>
                        <div><dt class="sr-only">Born</dt><dd>{{ $p->birth_date->format('d M Y') }}{{ $p->birth_time ? ', '.\Illuminate\Support\Carbon::createFromFormat('H:i:s', $p->birth_time)->format('g:i A') : ' · time unknown' }}</dd></div>
                        <div><dt class="sr-only">Place</dt><dd>{{ $p->place_label }}</dd></div>
                        @if ($p->utc_offset_seconds !== null)
                            <div><dt class="sr-only">Offset</dt><dd class="text-xs text-ink-600">UTC{{ $p->utc_offset_seconds >= 0 ? '+' : '−' }}{{ gmdate('H:i', abs($p->utc_offset_seconds)) }} ({{ $p->tz_name }}){{ $p->tz_resolution !== 'exact' ? ' · please confirm time' : '' }}</dd></div>
                        @endif
                    </dl>
                    <div class="mt-4 flex gap-2">
                        <a href="{{ route('account.birth-profiles.edit', $p) }}" class="btn-outline">Edit</a>
                        <form method="POST" action="{{ route('account.birth-profiles.destroy', $p) }}">@csrf @method('DELETE')<button class="btn-ghost px-3 text-danger-700">Delete</button></form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.account>
