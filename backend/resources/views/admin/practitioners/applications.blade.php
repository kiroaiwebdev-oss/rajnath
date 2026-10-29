<x-layouts.admin title="Astrologer applications">
    @if (session('invite_link'))<div class="card mb-4 border-gold-200 bg-gold-50 p-4 text-sm" role="status">Invitation link (also emailed): <code class="break-all">{{ session('invite_link') }}</code></div>@endif
    <nav class="mb-4 flex flex-wrap gap-2" aria-label="Status">
        @foreach (['submitted' => 'New', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'withdrawn' => 'Withdrawn'] as $k => $l)
            <a href="{{ route('admin.applications.index', ['status' => $k]) }}" @class(['btn px-4', 'bg-ink-900 text-white' => $status === $k, 'border border-ink-200 bg-white text-ink-800' => $status !== $k]) @if ($status === $k) aria-current="page" @endif>{{ $l }}</a>
        @endforeach
    </nav>
    @error('application')<p class="field-error mb-3" role="alert">{{ $message }}</p>@enderror
    @error('email')<p class="field-error mb-3" role="alert">{{ $message }}</p>@enderror
    <ul class="space-y-4">
        @forelse ($rows as $a)
            <li class="card p-5 text-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h2 class="text-base">{{ $a->name }}</h2><p class="text-ink-600">{{ $a->email }} · {{ $a->phone_e164 }}@if ($a->city) · {{ $a->city }}@endif</p></div>
                    <p class="text-xs text-ink-600">{{ \Illuminate\Support\Carbon::parse($a->created_at)->tz('Asia/Kolkata')->format('d M Y') }}</p>
                </div>
                <p class="mt-2"><strong>{{ $a->experience_years }} years</strong> · {{ $a->languages }} · {{ $a->specialties }}</p>
                <p class="mt-2 whitespace-pre-line leading-6">{{ $a->about }}</p>
                @if ($a->decision_note)<p class="mt-2 text-ink-600">Note: {{ $a->decision_note }}</p>@endif
                @if ($a->status === 'submitted')
                    @can('practitioners.manage')
                        <form method="POST" action="{{ route('admin.applications.decide', $a->ref) }}" class="mt-4 flex flex-wrap items-end gap-2">@csrf
                            <button name="decision" value="accept" class="btn-primary">Accept and send invitation</button>
                            <div class="min-w-[16rem] flex-1"><label class="sr-only" for="n-{{ $a->id }}">Reason for rejecting</label><input id="n-{{ $a->id }}" name="note" class="input" placeholder="Reason (shown to the applicant)"></div>
                            <button name="decision" value="reject" class="btn-outline">Reject</button>
                        </form>
                    @endcan
                @endif
            </li>
        @empty
            <li class="card p-8 text-center text-ink-600">No applications.</li>
        @endforelse
    </ul>
    <div class="mt-4">{{ $rows->links() }}</div>
    <p class="mt-4 text-xs text-ink-600">Accepting emails a practitioner invitation to the address in the application. The astrologer then completes their profile and payout details; the profile and commission are reviewed as usual before bookings open.</p>
</x-layouts.admin>
