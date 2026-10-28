<x-layouts.public title="Contact" description="Address, phone, WhatsApp and office timings of Acharya Dr. Rajnath Jha, Kankarbagh, Patna.">
    <div class="container-page grid gap-10 py-12 lg:grid-cols-2">
        <div>
            <p class="eyebrow">Contact</p>
            <h1 class="mt-2 text-3xl">Visit, call or write</h1>
            <div class="mt-6 space-y-4">
                <div class="card p-5">
                    <h2 class="text-base">Office</h2>
                    <p class="mt-2 text-ink-700">B-Block, 1st Floor, Chanakya Plaza,<br>Near Chandan Automobile, Kankarbagh Main Road,<br>Kankarbagh, Patna, Bihar 800020</p>
                    <a class="btn-outline mt-4" rel="noopener" href="https://www.openstreetmap.org/search?query={{ urlencode('Chanakya Plaza Kankarbagh Patna') }}">Open in map</a>
                </div>
                <div class="card p-5">
                    <h2 class="text-base">Phone & WhatsApp</h2>
                    <ul class="mt-2 space-y-1">
                        <li><a href="tel:+919835258203">+91 98352 58203</a></li>
                        <li><a href="tel:+919835882652">+91 98358 82652</a></li>
                        <li><a href="https://wa.me/919835258203" rel="noopener">Message on WhatsApp</a></li>
                        <li><a href="mailto:{{ $site('contact.email') }}" class="break-all">{{ $site('contact.email') }}</a></li>
                    </ul>
                </div>
                <div class="card p-5">
                    <h2 class="text-base">Timings (IST)</h2>
                    <table class="mt-2 w-full text-sm"><tbody>
                        <tr><th scope="row" class="py-1 pr-4 text-left font-medium">Mon – Wed</th><td>8:00 AM – 12:00 PM, 3:00 PM – 7:00 PM</td></tr>
                        <tr><th scope="row" class="py-1 pr-4 text-left font-medium">Thursday</th><td>Closed</td></tr>
                        <tr><th scope="row" class="py-1 pr-4 text-left font-medium">Fri – Sun</th><td>8:00 AM – 12:00 PM, 3:00 PM – 7:00 PM</td></tr>
                    </tbody></table>
                    <p class="mt-2 text-xs text-ink-600">Online consultations can be scheduled flexibly.</p>
                </div>
            </div>
        </div>
        <div>
            <div class="card p-5 sm:p-6">
                <h2 class="text-lg">Send a message</h2>
                <p class="mt-1 text-sm text-ink-600">Tell us what you need. We usually reply within one working day.</p>
                @include('public.partials.enquiry-form', ['offering' => null])
            </div>
        </div>
    </div>
</x-layouts.public>
