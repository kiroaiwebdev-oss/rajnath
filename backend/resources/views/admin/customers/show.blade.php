<x-layouts.admin :title="$c->name">
    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <section class="card p-5 sm:p-6">
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-ink-600">Email</dt><dd class="font-medium break-all">{{ $c->email ?? '—' }} {{ $c->email_verified_at ? '(verified)' : '' }}</dd></div>
                <div><dt class="text-ink-600">Mobile</dt><dd class="font-medium">{{ $c->phone_e164 ?? '—' }} {{ $c->phone_verified_at ? '(verified)' : '' }}</dd></div>
                <div><dt class="text-ink-600">Joined</dt><dd class="font-medium">{{ $c->created_at->tz('Asia/Kolkata')->format('d M Y') }}</dd></div>
                <div><dt class="text-ink-600">Last sign-in</dt><dd class="font-medium">{{ $c->last_login_at?->tz('Asia/Kolkata')->format('d M Y, g:i A') ?? '—' }}</dd></div>
                <div><dt class="text-ink-600">Birth profiles</dt><dd class="font-medium">{{ $profiles }} (details are private to the customer)</dd></div>
                <div><dt class="text-ink-600">Signed-in devices</dt><dd class="font-medium">{{ $devices }}</dd></div>
            </dl>
            <h2 class="mt-6 text-base">Recent enquiries</h2>
            <ul class="mt-2 space-y-2 text-sm">
                @forelse ($enquiries as $e)
                    <li><a href="{{ route('admin.enquiries.show', $e) }}">{{ $e->created_at->tz('Asia/Kolkata')->format('d M Y') }} — {{ \App\Models\Enquiry::STATUSES[$e->status] }}</a></li>
                @empty
                    <li class="text-ink-600">None.</li>
                @endforelse
            </ul>
        </section>
        @can('customers.restrict')
        <form method="POST" action="{{ route('admin.customers.status', $c) }}" class="card h-fit space-y-4 p-5">
            @csrf @method('PUT')
            <h2 class="text-lg">Account access</h2>
            <p class="text-sm">Status: <strong>{{ ucfirst($c->status) }}</strong></p>
            <input type="hidden" name="status" value="{{ $c->isActive() ? 'suspended' : 'active' }}">
            <x-field name="reason" label="Reason (recorded in the audit log)" required />
            <button class="{{ $c->isActive() ? 'btn-danger' : 'btn-primary' }} w-full">{{ $c->isActive() ? 'Suspend & sign out everywhere' : 'Reactivate' }}</button>
        </form>
        @endcan
    </div>
</x-layouts.admin>
