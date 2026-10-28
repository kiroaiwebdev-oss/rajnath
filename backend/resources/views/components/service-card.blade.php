@props(['o'])
@php $t = $o->t(); $from = $o->fromPriceMinor(); @endphp
<article class="card flex h-full flex-col overflow-hidden">
    @if ($o->image_path)
        <x-photo :src="$o->image_path" alt="" class="aspect-[4/3] w-full object-cover object-top" />
    @endif
    <div class="flex flex-1 flex-col p-5">
        <p class="eyebrow">{{ $o->family->name }}</p>
        <h3 class="mt-1.5 text-base"><a href="{{ route('services.show', $o->slug) }}" class="no-underline after:absolute after:inset-0 hover:underline">{{ $t?->title }}</a></h3>
        <p class="mt-2 flex-1 text-sm text-ink-600">{{ $t?->summary }}</p>
        <p class="mt-4 text-sm font-semibold text-ink-900">{{ $from === null ? 'Price on request' : 'From '.\App\Support\Money::format($from) }}</p>
    </div>
</article>
