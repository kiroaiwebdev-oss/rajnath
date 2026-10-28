@php
    $fin = array_filter([
        ['Overview', 'admin.finance.overview', 'finance.view'],
        ['Payments', 'admin.finance.payments', 'finance.view'],
        ['Refunds', 'admin.finance.refunds', 'finance.view'],
        ['Disputes', 'admin.finance.disputes', 'finance.view'],
        ['Recharge options', 'admin.finance.packages', 'finance.view'],
        ['Razorpay setup', 'admin.payments-setup.edit', 'providers.manage'],
    ], fn ($i) => auth()->user()->can($i[2]));
@endphp
<nav aria-label="Finance sections" class="-mx-4 mb-6 overflow-x-auto px-4">
    <ul class="flex min-w-max gap-2">
        @foreach ($fin as [$label, $route])
            @php $on = request()->routeIs($route) || request()->routeIs($route.'.*'); @endphp
            <li><a href="{{ route($route) }}" @class(['btn px-4', 'bg-ink-900 text-white' => $on, 'border border-ink-200 bg-white text-ink-800' => ! $on]) @if ($on) aria-current="page" @endif>{{ $label }}</a></li>
        @endforeach
    </ul>
</nav>
