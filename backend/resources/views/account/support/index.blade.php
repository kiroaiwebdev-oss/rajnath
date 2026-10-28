<x-layouts.account title="Help & support">
    <div class="grid gap-6 lg:grid-cols-[1fr_24rem]">
        <section class="min-w-0" aria-labelledby="t-h">
            <h2 id="t-h" class="text-lg">Your requests</h2>
            @forelse ($rows as $t)
                <a href="{{ route('account.support.show', $t->ref) }}" class="card mt-3 flex flex-wrap items-center justify-between gap-3 p-4 no-underline">
                    <span><span class="font-medium text-ink-900">{{ $t->subject }}</span><span class="block text-xs text-ink-600">{{ \App\Domain\Support\Tickets::CATEGORIES[$t->category] ?? $t->category }} · updated {{ \Illuminate\Support\Carbon::parse($t->updated_at)->diffForHumans() }}</span></span>
                    <span @class(['badge-gold' => $t->status === 'waiting_customer', 'badge-success' => $t->status === 'resolved', 'badge-ink' => ! in_array($t->status, ['waiting_customer', 'resolved'])])>{{ \App\Domain\Support\Tickets::STATUSES[$t->status] }}</span>
                </a>
            @empty
                <p class="card mt-3 p-6 text-sm text-ink-600">No requests yet.</p>
            @endforelse
        </section>
        <form method="POST" action="{{ route('account.support.store') }}" class="card h-fit space-y-4 p-5">
            @csrf <h2 class="text-lg">New request</h2>
            <x-select name="category" label="About" :options="\App\Domain\Support\Tickets::CATEGORIES" />
            <x-select name="order_ref" label="Related order (optional)" :options="['' => 'None'] + $orders->mapWithKeys(fn ($o) => [$o->ref => \App\Domain\Payments\Checkout::label($o->kind, json_decode($o->snapshot, true)).' · '.\Illuminate\Support\Carbon::parse($o->created_at)->format('d M')])->all()" />
            <x-field name="subject" label="Subject" required />
            <div><label for="f-body" class="field-label">Message</label><textarea id="f-body" name="body" rows="5" class="input" required>{{ old('body') }}</textarea>@error('body')<p class="field-error">{{ $message }}</p>@enderror</div>
            <button class="btn-primary w-full">Send</button>
        </form>
    </div>
</x-layouts.account>
