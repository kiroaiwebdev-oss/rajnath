<x-layouts.public title="Privacy policy">
    <article class="container-page max-w-3xl py-12 leading-7">
        <p class="eyebrow">Legal</p>
        <h1 class="mt-2 text-3xl">Privacy policy</h1>
        <p class="mt-2 text-sm text-ink-600">Version {{ \App\Domain\Identity\CustomerAuth::TERMS_VERSION }}</p>

        <div class="mt-8 space-y-6">
            <p>This policy explains what personal information {{ $site('brand.name') }} ("we") collects through this website and app, why, and the choices you have. Contact: <a href="mailto:{{ $site('contact.email') }}">{{ $site('contact.email') }}</a>, {{ $site('contact.phone') }}.</p>

            <section><h2 class="text-xl">What we collect</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li><strong>Account details:</strong> name, email address and/or mobile number, language and notification preferences.</li>
                    <li><strong>Birth details</strong> you choose to save: name, date, time and place of birth, and gender.</li>
                    <li><strong>Requests and messages</strong> you send us, including the service, preferred date and your question.</li>
                    <li><strong>Security information:</strong> sign-in times, signed-in devices, IP address and browser type, and records of important account actions.</li>
                </ul>
            </section>

            <section><h2 class="text-xl">Why we use it</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>To provide the consultations, reports and services you ask for, and to contact you about them.</li>
                    <li>To sign you in with one-time codes and keep your account secure.</li>
                    <li>To meet legal, tax and accounting obligations.</li>
                    <li>To send optional updates only if you switch them on in your account.</li>
                </ul>
                <p class="mt-2">We do not sell your personal information.</p>
            </section>

            <section><h2 class="text-xl">Who processes it for us</h2>
                <p class="mt-2">We use service providers only to run the platform: our hosting provider, the email provider and SMS provider that deliver sign-in codes and notices, and (when online payments open) our payment provider. They may process your data only on our instructions.</p>
            </section>

            <section><h2 class="text-xl">Confidentiality of consultations</h2>
                <p class="mt-2">Birth details and what you discuss are confidential. Staff access to personal records is limited by role and recorded in an audit log. Consultations are not recorded unless you are told beforehand and agree.</p>
            </section>

            <section><h2 class="text-xl">How long we keep it</h2>
                <p class="mt-2">We keep information while your account is active and as long as needed for the purposes above and for legal requirements. When you ask us to delete your account, we delete or anonymise your information except what the law requires us to keep (for example, invoices).</p>
            </section>

            <section><h2 class="text-xl">Your choices and rights</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>View and correct your details and birth profiles in <a href="{{ route('account.dashboard') }}">your account</a>.</li>
                    <li>Change notification preferences, and sign out other devices.</li>
                    <li>Ask for a copy of your data or deletion of your account by writing to us. Self-service export and deletion are being added to the account area.</li>
                </ul>
            </section>

            <section><h2 class="text-xl">Security</h2>
                <p class="mt-2">Connections are encrypted, secrets are stored encrypted, and staff accounts require two-factor authentication. No system is perfectly secure; please keep your sign-in codes private — we will never ask you for them.</p>
            </section>

            <section><h2 class="text-xl">Place data</h2>
                <p class="mt-2">Birth-place search uses GeoNames data (CC BY 4.0).</p>
            </section>
        </div>
    </article>
</x-layouts.public>
