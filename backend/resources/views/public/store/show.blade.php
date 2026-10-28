<x-layouts.public :title="$p->name" :description="$p->summary" :image="$p->image_path">
    <div class="container-page py-10">
        <nav aria-label="Breadcrumb" class="text-sm text-ink-600"><a href="{{ route('store.index') }}">Store</a> / <span class="text-ink-900">{{ $p->name }}</span></nav>
        <div class="mt-6 grid gap-10 lg:grid-cols-[1fr_26rem]">
            <article class="min-w-0">
                @if ($p->image_path)<x-photo :src="$p->image_path" :alt="$p->name" eager class="aspect-square w-full max-w-lg rounded-[var(--radius-card)] object-cover" />@endif
                <h1 class="mt-6 text-3xl">{{ $p->name }}</h1>
                <p class="mt-2 text-lg text-ink-600">{{ $p->summary }}</p>
                <dl class="mt-6 grid gap-3 sm:grid-cols-3">
                    @foreach (['Weight' => $p->weight, 'Origin' => $p->origin, 'Certificate' => $p->certification] as $k => $v)
                        @if ($v)<div class="card p-3"><dt class="text-xs text-ink-600">{{ $k }}</dt><dd class="font-medium">{{ $v }}</dd></div>@endif
                    @endforeach
                </dl>
                @if ($p->description)<div class="mt-6 space-y-4 leading-7">@foreach (preg_split('/\R{2,}/', $p->description) as $para)<p>{{ $para }}</p>@endforeach</div>@endif
                <p class="mt-8 rounded-2xl border border-gold-200 bg-gold-50 p-4 text-sm"><strong>Please note:</strong> Gemstones are a traditional remedy and do not guarantee any result or replace medical, legal or financial advice. Wear a stone only after a chart consultation. Natural stones vary in colour and inclusions.</p>
            </article>
            <aside class="lg:sticky lg:top-24 lg:self-start">
                <div class="card p-5 sm:p-6">
                    <p class="text-2xl font-semibold">{{ \App\Support\Money::format((int) $p->price_minor) }}</p>
                    <p class="text-sm text-ink-600">Includes insured shipping within India.</p>
                    @if (! $p->stock)
                        <p class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-danger-700">Out of stock.</p>
                    @elseif (! $payable)
                        <p class="mt-4 rounded-xl bg-ivory-100 p-3 text-sm">Online ordering opens once payments are switched on. Call {{ $site('contact.phone') }} to order.</p>
                    @elseif (! auth()->check() || auth()->user()->isStaff())
                        <a href="{{ route('account.sign-in') }}" class="btn-primary mt-4 w-full">Sign in to order</a>
                    @else
                        <form method="POST" action="{{ route('store.buy') }}" class="mt-4 space-y-3" novalidate>
                            @csrf
                            <input type="hidden" name="product" value="{{ $p->ref }}"><input type="hidden" name="key" value="{{ \App\Http\Controllers\Web\PaymentController::newKey() }}">
                            @error('product')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            <x-field name="quantity" label="Quantity" type="number" :value="1" min="1" :max="min($p->stock, $p->max_per_order)" required />
                            <fieldset class="space-y-3 rounded-2xl border border-ink-200 p-4"><legend class="px-1 text-sm font-semibold">Delivery address (India)</legend>
                                <x-field name="ship_name" label="Full name" :value="auth()->user()->name" autocomplete="name" required />
                                <x-field name="ship_phone" label="Mobile" type="tel" :value="auth()->user()->phone_e164" autocomplete="tel" required />
                                <x-field name="ship_line1" label="House / street" autocomplete="address-line1" required />
                                <x-field name="ship_line2" label="Area / landmark (optional)" autocomplete="address-line2" />
                                <div class="grid grid-cols-2 gap-3"><x-field name="ship_city" label="City" autocomplete="address-level2" required /><x-field name="ship_pincode" label="PIN code" inputmode="numeric" autocomplete="postal-code" required /></div>
                                <x-field name="ship_state" label="State" autocomplete="address-level1" required />
                            </fieldset>
                            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="agree" value="1" class="mt-1 size-4 accent-ink-900" @checked(old('agree'))> <span>I have read the note about gemstones and the <a href="{{ route('terms') }}">terms</a>.</span></label>
                            @error('agree')<p class="field-error">{{ $message }}</p>@enderror
                            <button class="btn-primary w-full">Continue to payment</button>
                        </form>
                    @endif
                </div>
            </aside>
        </div>
    </div>
</x-layouts.public>
