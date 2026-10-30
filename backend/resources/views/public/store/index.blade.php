<x-layouts.public :title="__('Gemstone store')" :description="__('Certified gemstones, Rudraksha and remedies recommended by Acharya Dr. Rajnath Jha, delivered across India.')" :jsonld="[\App\Support\Seo\JsonLd::breadcrumbs([[__('Gemstone store'), route('store.index')]])]">
    <div class="container-page py-10">
        <p class="eyebrow">Store</p>
        <h1 class="mt-2 text-3xl">Gemstones & remedies</h1>
        <p class="mt-2 max-w-2xl text-ink-600">Each item shows its weight, origin and certificate details as supplied. Please wear a gemstone only after a chart consultation — the wrong stone can be unsuitable.</p>
        <nav aria-label="Category" class="mt-6"><ul class="flex flex-wrap gap-2">
            @foreach (['' => 'All'] + \App\Domain\Store\Store::CATEGORIES as $k => $l)
                <li><a href="{{ route('store.index', array_filter(['category' => $k])) }}" @class(['btn px-4', 'bg-ink-900 text-white' => $category === $k, 'border border-ink-200 bg-white text-ink-800' => $category !== $k]) @if ($category === $k) aria-current="page" @endif>{{ $l }}</a></li>
            @endforeach
        </ul></nav>
        @if ($products->isEmpty())
            <div class="card mt-6 p-8 text-center"><h2 class="text-base">No items available right now</h2><p class="mt-2 text-sm text-ink-600">New stock is added regularly. For a personal recommendation, <a href="{{ route('services.show', 'gemstone-guidance') }}">book gemstone guidance</a>.</p></div>
        @else
            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($products as $p)
                    <article class="card relative flex flex-col overflow-hidden">
                        @if ($p->image_path)<x-photo :src="$p->image_path" alt="" class="aspect-square w-full object-cover" />@else<div class="aspect-square w-full bg-ivory-100" aria-hidden="true"></div>@endif
                        <div class="flex flex-1 flex-col p-4">
                            <p class="eyebrow">{{ \App\Domain\Store\Store::CATEGORIES[$p->category] }}</p>
                            <h2 class="mt-1 text-base"><a href="{{ route('store.show', $p->slug) }}" class="no-underline after:absolute after:inset-0">{{ $p->name }}</a></h2>
                            @if ($p->weight)<p class="text-sm text-ink-600">{{ $p->weight }}</p>@endif
                            <p class="mt-auto pt-3 font-semibold">{{ \App\Support\Money::format((int) $p->price_minor) }}</p>
                            <p class="text-xs {{ $p->stock ? 'text-success-700' : 'text-danger-700' }}">{{ $p->stock ? ($p->stock <= 3 ? 'Only '.$p->stock.' left' : 'In stock') : 'Out of stock' }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.public>
