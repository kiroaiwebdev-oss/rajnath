<x-layouts.admin :title="'Enquiry from '.$e->name">
    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <section class="card p-5 sm:p-6">
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                @foreach ([
                    'Service' => $e->offering?->t()?->title ?? 'General enquiry',
                    'Received' => $e->created_at->tz('Asia/Kolkata')->format('d M Y, g:i A').' IST',
                    'Phone' => $e->phone_e164, 'Email' => $e->email ?? '—',
                    'Mode' => $e->mode ? (\App\Models\OfferingVariant::MODES[$e->mode] ?? $e->mode) : 'No preference',
                    'Language' => $e->language ?? '—', 'Preferred date' => $e->preferred_date?->format('d M Y') ?? '—',
                    'Birth details' => $e->birth_date ? $e->birth_date->format('d M Y').($e->birth_time ? ' '.substr($e->birth_time, 0, 5) : '').($e->birth_place ? ', '.$e->birth_place : '') : '—',
                    'Account' => $e->user_id ? 'Signed-in customer' : 'Guest', 'Reference' => $e->ref,
                ] as $k => $v)
                    <div><dt class="text-ink-600">{{ $k }}</dt><dd class="mt-0.5 font-medium break-words">{{ $v }}</dd></div>
                @endforeach
            </dl>
            <h2 class="mt-6 text-sm text-ink-600">Message</h2>
            <p class="mt-1 whitespace-pre-line rounded-xl bg-ivory-100 p-4 text-sm">{{ $e->message ?: '—' }}</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="tel:{{ $e->phone_e164 }}" class="btn-primary">Call</a>
                <a href="https://wa.me/{{ ltrim($e->phone_e164, '+') }}" rel="noopener" class="btn-outline">WhatsApp</a>
                @if ($e->email)<a href="mailto:{{ $e->email }}" class="btn-outline">Email</a>@endif
            </div>
        </section>
        @can('bookings.manage')
        <form method="POST" action="{{ route('admin.enquiries.update', $e) }}" class="card h-fit space-y-4 p-5">
            @csrf @method('PUT')
            <h2 class="text-lg">Follow-up</h2>
            <x-select name="status" label="Status" :value="$e->status" :options="\App\Models\Enquiry::STATUSES" />
            <div><label for="f-note" class="field-label">Internal note (not shown to the customer)</label><textarea id="f-note" name="staff_note" rows="4" class="input">{{ old('staff_note', $e->staff_note) }}</textarea></div>
            <button class="btn-primary w-full">Save</button>
        </form>
        @endcan
    </div>
</x-layouts.admin>
