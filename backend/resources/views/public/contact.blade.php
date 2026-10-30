@php $B = app(\App\Support\Business::class); $tel = fn ($p) => preg_replace('/^\+91(\d{5})(\d{5})$/', '+91 $1 $2', $p); @endphp
<x-layouts.public :title="__('Contact')" :description="__('Address, phone, WhatsApp and office timings of Acharya Dr. Rajnath Jha, Kankarbagh, Patna.')"
    :jsonld="[['@type' => 'ContactPage', 'url' => route('contact'), 'about' => ['@id' => \App\Support\Seo\JsonLd::id('business')]], \App\Support\Seo\JsonLd::breadcrumbs([[__('Contact'), route('contact')]])]">
    <div class="container-page grid gap-10 py-12 lg:grid-cols-2">
        <div>
            <p class="eyebrow">{{ __('Contact') }}</p>
            <h1 class="mt-2 text-3xl">{{ __('Visit, call or write') }}</h1>
            <div class="mt-6 space-y-4">
                <div class="card p-5">
                    <h2 class="text-base">{{ __('Office') }}</h2>
                    <address class="mt-2 not-italic text-ink-700">{{ $B->address()['street'] }},<br>{{ $B->address()['locality'] }}, {{ $B->address()['region'] }} {{ $B->address()['postal_code'] }}</address>
                    <a class="btn-outline mt-4" rel="noopener" href="{{ $B->mapUrl() }}">{{ __('Open in map') }}</a>
                </div>
                <div class="card p-5">
                    <h2 class="text-base">{{ __('Phone & WhatsApp') }}</h2>
                    <ul class="mt-2 space-y-1">
                        @foreach (array_filter([$B->phone(), $B->phone2()]) as $p)<li><a href="tel:{{ $p }}">{{ $tel($p) }}</a></li>@endforeach
                        @if ($B->whatsapp())<li><a href="https://wa.me/{{ ltrim($B->whatsapp(), '+') }}" rel="noopener">{{ __('Message on WhatsApp') }}</a></li>@endif
                        @if ($B->email())<li><a href="mailto:{{ $B->email() }}" class="break-all">{{ $B->email() }}</a></li>@endif
                    </ul>
                </div>
                <div class="card p-5">
                    <h2 class="text-base">{{ __('Timings (IST)') }}</h2>
                    <table class="mt-2 w-full text-sm"><tbody>
                        @foreach ($B->hoursRows() as [$d, $h])<tr><th scope="row" class="py-1 pr-4 text-left font-medium">{{ __($d) }}</th><td>{{ __($h) }}</td></tr>@endforeach
                    </tbody></table>
                    <p class="mt-2 text-xs text-ink-600">{{ __('Online consultations can be scheduled flexibly.') }}</p>
                </div>
            </div>
        </div>
        <div>
            <div class="card p-5 sm:p-6">
                <h2 class="text-lg">{{ __('Send a message') }}</h2>
                <p class="mt-1 text-sm text-ink-600">{{ __('Tell us what you need. We usually reply within one working day.') }}</p>
                @include('public.partials.enquiry-form', ['offering' => null])
            </div>
        </div>
    </div>
</x-layouts.public>
