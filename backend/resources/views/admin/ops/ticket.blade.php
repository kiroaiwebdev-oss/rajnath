<x-layouts.admin :title="$t->subject">
    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <section class="min-w-0">
            <p class="text-sm text-ink-600">{{ $customer->name }} · {{ \App\Domain\Support\Tickets::CATEGORIES[$t->category] ?? '' }} · {{ \App\Domain\Support\Tickets::STATUSES[$t->status] }}
                @if ($order) · Order <a href="{{ route('admin.finance.order', $order->ref) }}">{{ substr($order->ref, -10) }}</a>@endif</p>
            <ol class="mt-4 space-y-3">
                @foreach ($messages as $m)
                    <li @class(['card p-4', 'border-gold-200 bg-gold-50' => $m->internal, 'bg-ivory-100' => ! $m->internal && $m->kind === 'staff'])>
                        <p class="text-xs text-ink-600">{{ $m->name }} @if ($m->internal)<span class="badge-gold ml-1">Internal note</span>@endif · {{ \Illuminate\Support\Carbon::parse($m->created_at)->tz('Asia/Kolkata')->format('d M, g:i A') }}</p>
                        <p class="mt-1 whitespace-pre-line text-sm">{{ $m->body }}</p>
                    </li>
                @endforeach
            </ol>
        </section>
        @can('support.handle')
        <form method="POST" action="{{ route('admin.ops.ticket.reply', $t->ref) }}" class="card h-fit space-y-3 p-5">
            @csrf <label for="f-body" class="field-label">Reply or note</label><textarea id="f-body" name="body" rows="5" class="input" required></textarea>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="internal" value="1" class="size-4 accent-ink-900"> Internal note (customer does not see it)</label>
            <x-select name="status" label="Set status" :options="['' => 'Automatic'] + \App\Domain\Support\Tickets::STATUSES" />
            <button class="btn-primary w-full">Save</button>
            <p class="text-xs text-ink-600">Refunds: use Finance → order → Request refund (needs a second approver).</p>
        </form>
        @endcan
    </div>
</x-layouts.admin>
