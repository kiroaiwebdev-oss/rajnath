@php $qa = \App\Support\Seo\JsonLd::serviceQuestions($o); @endphp
<x-layouts.public :title="$t->title" :description="$t->meta_description ?: $t->summary" :image="$o->image_path" :jsonld="[\App\Support\Seo\JsonLd::service($o), \App\Support\Seo\JsonLd::faq($qa), \App\Support\Seo\JsonLd::breadcrumbs([[__('Services'), route('services.index')], [$o->family->label(), route('services.index', ['family' => $o->family->slug])], [$t->title, route('services.show', $o->slug)]])]">
    <div class="container-page py-10">
        <nav aria-label="Breadcrumb" class="text-sm text-ink-600">
            <ol class="flex flex-wrap gap-1.5">
                <li><a href="{{ route('services.index') }}">{{ __('Services') }}</a> /</li>
                <li><a href="{{ route('services.index', ['family' => $o->family->slug]) }}">{{ $o->family->label() }}</a> /</li>
                <li aria-current="page" class="text-ink-900">{{ $t->title }}</li>
            </ol>
        </nav>

        <div class="mt-6 grid gap-10 lg:grid-cols-[1fr_24rem]">
            <article class="min-w-0">
                <h1 class="text-3xl sm:text-4xl">{{ $t->title }}</h1>
                <p class="mt-3 text-lg text-ink-600">{{ $t->summary }}</p>
                @if ($o->image_path)
                    <x-photo :src="$o->image_path" alt="" class="mt-6 aspect-[16/9] w-full rounded-[var(--radius-card)] object-cover object-top" />
                @endif
                @if ($t->body)
                    <div class="mt-8 space-y-4 leading-7">@foreach (preg_split('/\R{2,}/', $t->body) as $para)<p>{{ $para }}</p>@endforeach</div>
                @endif
                @if ($t->includes)
                    <h2 class="mt-10 text-xl">{{ __("What's included") }}</h2>
                    <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                        @foreach ($t->includes as $i)
                            <li class="card flex gap-3 p-4 text-sm"><span aria-hidden="true" class="mt-0.5 text-gold-700">✓</span><span>{{ $i }}</span></li>
                        @endforeach
                    </ul>
                @endif
                @if ($t->audience)
                    <h2 class="mt-10 text-xl">{{ __('Who it is for') }}</h2>
                    <ul class="mt-4 list-disc space-y-1.5 pl-5 text-ink-700">@foreach ($t->audience as $a)<li>{{ $a }}</li>@endforeach</ul>
                @endif
                @if ($t->exclusions)<h2 class="mt-10 text-xl">{{ __('Not included') }}</h2><p class="mt-3 text-ink-700">{{ $t->exclusions }}</p>@endif
                <section class="mt-10" aria-labelledby="qa-h">
                    <h2 id="qa-h" class="text-xl">{{ __('Quick answers') }}</h2>
                    <dl class="mt-4 space-y-3">@foreach ($qa as [$q, $a])<div class="card p-4"><dt class="font-semibold">{{ $q }}</dt><dd class="mt-1 text-sm text-ink-700">{{ $a }}</dd></div>@endforeach</dl>
                </section>
                @if ($t->disclaimer)
                    <p class="mt-10 rounded-2xl border border-gold-200 bg-gold-50 p-4 text-sm text-ink-700"><strong>{{ __('Please note:') }}</strong> {{ $t->disclaimer }}</p>
                @endif
            </article>

            <aside class="lg:sticky lg:top-24 lg:self-start" aria-labelledby="book-h">
                <div class="card p-5 sm:p-6">
                    <h2 id="book-h" class="text-lg">{{ __('Options & prices') }}</h2>
                    <ul class="mt-4 divide-y divide-ink-200/60">
                        @foreach ($o->variants->where('active', true) as $v)
                            <li class="flex items-start justify-between gap-3 py-3 text-sm">
                                <span><span class="font-medium">{{ $v->label() }}</span>
                                    <span class="block text-xs text-ink-600">{{ implode(' · ', array_filter([$v->duration_min ? __(':n min', ['n' => $v->duration_min]) : null, $v->participants > 1 ? __(':n people', ['n' => $v->participants]) : null, $v->urgent ? __('Priority') : null])) }}</span></span>
                                <span class="whitespace-nowrap font-semibold">{{ \App\Support\Money::format($v->price_minor) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @php $payable = app(\App\Domain\Payments\Razorpay::class)->configured() && $o->variants->where('active', true)->whereNotNull('price_minor')->isNotEmpty(); @endphp
                    @if ($payable)
                        @auth
                        <form method="POST" action="{{ route('checkout.buy') }}" class="mt-4 space-y-3">
                            @csrf <input type="hidden" name="key" value="{{ \App\Http\Controllers\Web\PaymentController::newKey() }}">
                            <x-select name="variant" :label="__('Choose option')" :options="$o->variants->where('active', true)->whereNotNull('price_minor')->mapWithKeys(fn ($v) => [$v->ref => $v->label().' — '.\App\Support\Money::format($v->price_minor)])->all()" />
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="use_wallet" value="1" class="size-4 accent-ink-900"> {{ __('Use wallet balance first') }}</label>
                            <x-field name="coupon" :label="__('Coupon code (optional)')" :value="old('coupon')" autocomplete="off" maxlength="32" class="input uppercase" />
                            @error('variant')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            <button class="btn-primary w-full">{{ __('Pay & book') }}</button>
                        </form>
                        <p class="mt-3 text-xs text-ink-600">{{ __('After payment our team contacts you to fix the time.') }}</p>
                        @else
                        <a href="{{ route('account.sign-in') }}" class="btn-primary mt-4 w-full">{{ __('Sign in to pay & book') }}</a>
                        @endauth
                    @else
                        <p class="mt-3 rounded-xl bg-ivory-100 p-3 text-xs text-ink-700">{{ __('Online payment is not open for this service yet. Send a request below and our team will call you to confirm the time and fee.') }}</p>
                    @endif
                </div>
                <div class="card mt-5 p-5 sm:p-6" id="request">
                    <h2 class="text-lg">{{ __('Request this service') }}</h2>
                    @include('public.partials.enquiry-form', ['offering' => $o])
                </div>
            </aside>
        </div>

        @php $rv = app(\App\Domain\Reviews\Reviews::class); $svcReviews = $rv->forOffering($o->id, 4); $svcSummary = $rv->summary($o->id); @endphp
        @if ($svcReviews->isNotEmpty())
            <section class="mt-16" aria-labelledby="rev-h">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 id="rev-h" class="text-xl">{{ __('What clients say') }}</h2>
                        <p class="mt-1 flex items-center gap-2 text-sm text-ink-600"><x-stars :rating="$svcSummary['average']" /> {{ number_format($svcSummary['average'], 1) }} · {{ trans_choice(':count review|:count reviews', $svcSummary['count'], ['count' => $svcSummary['count']]) }}</p>
                    </div>
                    <a href="{{ route('reviews', ['service' => $o->slug]) }}" class="btn-outline">{{ __('All reviews') }}</a>
                </div>
                <div class="mt-5 grid gap-5 md:grid-cols-2">@foreach ($svcReviews as $r)<x-review-card :r="$r" clamp />@endforeach</div>
            </section>
        @endif

        @if ($related->isNotEmpty())
            <section class="mt-16" aria-labelledby="rel-h">
                <h2 id="rel-h" class="text-xl">{{ __('Related services') }}</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">@foreach ($related as $r)<div class="relative"><x-service-card :o="$r" /></div>@endforeach</div>
            </section>
        @endif
    </div>
</x-layouts.public>
