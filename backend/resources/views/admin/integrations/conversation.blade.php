<x-layouts.admin title="Assistant conversation">
    <p class="text-sm text-ink-600">{{ $customer ? $customer->name.' · '.($customer->email ?? $customer->phone_e164) : 'Visitor (not signed in)' }} · {{ $c->channel }} · started {{ \Illuminate\Support\Carbon::parse($c->created_at)->format('d M Y, g:i A') }}
        @if ($customer) · <a href="{{ route('admin.customers.show', $customer->ref) }}">customer record</a>@endif</p>
    <ol class="mt-5 max-w-3xl space-y-3">
        @foreach ($messages as $m)
            <li class="card p-4 {{ $m->role === 'user' ? '' : 'bg-ivory-100' }}">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-600">{{ $m->role === 'user' ? 'Customer' : 'Assistant' }}
                    @if ($m->provider) · {{ $m->provider }} {{ $m->model }} · {{ $m->latency_ms }} ms @endif
                    @if ($m->flag)<span class="badge-danger ml-1">{{ $m->flag }}</span>@endif</p>
                <p class="mt-1 whitespace-pre-line">{{ $m->content }}</p>
            </li>
        @endforeach
    </ol>
</x-layouts.admin>
