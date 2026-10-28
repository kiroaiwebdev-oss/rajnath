@props(['title'])
@php
    $tabs = [['Overview', 'account.dashboard'], ['Orders', 'account.orders'], ['Wallet', 'account.wallet'], ['Palmistry', 'account.palm.index'], ['Birth profiles', 'account.birth-profiles.index'], ['Profile & contact', 'account.profile'], ['Devices', 'account.devices']];
@endphp
<x-layouts.public :title="$title">
    <div class="container-page py-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="eyebrow">My account</p>
                <h1 class="mt-1 text-2xl">{{ $title }}</h1>
            </div>
            <form method="POST" action="{{ route('account.sign-out') }}">@csrf<button class="btn-outline">Sign out</button></form>
        </div>
        <nav aria-label="Account" class="-mx-4 mt-5 overflow-x-auto px-4">
            <ul class="flex min-w-max gap-2 border-b border-ink-200/70 pb-2">
                @foreach ($tabs as [$label, $route])
                    @php $on = request()->routeIs(str_replace('.index', '', $route).'*'); @endphp
                    <li><a href="{{ route($route) }}" @class(['btn px-4', 'bg-ink-900 text-white' => $on, 'text-ink-700 hover:bg-ivory-100' => ! $on]) @if ($on) aria-current="page" @endif>{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>
        @if ($errors->any())
            <div role="alert" class="card mt-5 border-red-200 bg-red-50 px-4 py-3 text-sm text-danger-700"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif
        <div class="mt-6">{{ $slot }}</div>
    </div>
</x-layouts.public>
