<x-layouts.admin title="Email & SMS setup">
    <p class="max-w-3xl text-sm text-ink-600">Enter your provider details here. No files need editing. Passwords and API keys are stored encrypted and are never shown again after saving. Saving or sending a test asks you to confirm your password and authenticator code.</p>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        {{-- EMAIL --}}
        <section aria-labelledby="mail-h" class="card min-w-0 p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="mail-h" class="text-lg">Email (SMTP)</h2>
                @if ($mailSource === 'admin')<span class="badge-success">Using these settings</span>
                @elseif ($mailSource === 'log')<span class="badge-danger">Not configured — emails are only written to the log</span>
                @else<span class="badge-ink">Using server .env settings</span>@endif
            </div>
            <form method="POST" action="{{ route('admin.providers.mail') }}" class="mt-5 space-y-5">
                @csrf @method('PUT')
                <div class="grid gap-5 sm:grid-cols-2">
                    @include('admin.providers._fields', ['fields' => \App\Domain\Messaging\ProviderConfig::MAIL_FIELDS])
                </div>
                <button class="btn-primary">Save email settings</button>
            </form>

            <form method="POST" action="{{ route('admin.providers.mail.test') }}" class="mt-6 border-t border-ink-200/60 pt-5">
                @csrf
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex-1"><x-field name="test_email" label="Send a test email to" type="email" :value="auth()->user()->email" /></div>
                    <button class="btn-outline">Send test email</button>
                </div>
            </form>
        </section>

        {{-- SMS --}}
        <section aria-labelledby="sms-h" class="card min-w-0 p-5 sm:p-6" x-data="smsPicker" data-driver="{{ old('sms_driver', $values['sms.driver']) }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="sms-h" class="text-lg">SMS OTP</h2>
                @if ($values['sms.driver'] === 'none')<span class="badge-danger">Not configured</span>
                @elseif ($smsError)<span class="badge-danger">Incomplete</span>
                @else<span class="badge-success">{{ \App\Domain\Messaging\ProviderConfig::SMS_DRIVERS[$values['sms.driver']] }} active</span>@endif
            </div>
            @if ($smsError && $values['sms.driver'] !== 'none')<p class="mt-2 text-sm text-danger-700">{{ $smsError }}</p>@endif

            <form method="POST" action="{{ route('admin.providers.sms') }}" class="mt-5 space-y-5">
                @csrf @method('PUT')
                <x-select name="sms_driver" label="SMS provider" :options="\App\Domain\Messaging\ProviderConfig::SMS_DRIVERS" :value="$values['sms.driver']" @change="pick"
                          hint="Choose one. The settings for the other provider are kept, so you can switch back any time." />

                <fieldset x-show="isTwilio" class="rounded-2xl border border-ink-200 p-4">
                    <legend class="px-1 text-sm font-semibold">Twilio</legend>
                    <div class="grid gap-5 sm:grid-cols-2">
                        @include('admin.providers._fields', ['fields' => \App\Domain\Messaging\ProviderConfig::SMS_FIELDS['twilio']])
                    </div>
                    <p class="mt-3 text-xs text-ink-600">Find these in the Twilio Console › Account info. Indian numbers need your sender and template registered on DLT through Twilio.</p>
                </fieldset>

                <fieldset x-show="isFast2sms" class="rounded-2xl border border-ink-200 p-4">
                    <legend class="px-1 text-sm font-semibold">Fast2SMS</legend>
                    <div class="grid gap-5 sm:grid-cols-2">
                        @include('admin.providers._fields', ['fields' => \App\Domain\Messaging\ProviderConfig::SMS_FIELDS['fast2sms']])
                    </div>
                    <p class="mt-3 text-xs text-ink-600">Fast2SMS sends only to Indian (+91) numbers. For customers abroad, choose "Send via Twilio" below and fill in the Twilio settings too.</p>
                </fieldset>

                <div class="grid gap-5 sm:grid-cols-2" x-show="hasProvider">
                    @include('admin.providers._fields', ['fields' => \App\Domain\Messaging\ProviderConfig::COMMON_SMS_FIELDS])
                </div>
                <button class="btn-primary">Save SMS settings</button>
            </form>

            <form method="POST" action="{{ route('admin.providers.sms.test') }}" class="mt-6 border-t border-ink-200/60 pt-5">
                @csrf
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex-1"><x-field name="test_phone" label="Send a test OTP to" inputmode="tel" placeholder="98xxxxxxxx or +44…" hint="Uses one SMS credit." /></div>
                    <button class="btn-outline">Send test SMS</button>
                </div>
            </form>
        </section>
    </div>

    <section aria-labelledby="log-h" class="mt-8">
        <h2 id="log-h" class="text-base">Recent email & SMS attempts</h2>
        <p class="mt-1 text-xs text-ink-600">"Accepted" means the provider accepted the message. It does not yet confirm it reached the phone or inbox.</p>
        <div class="table-wrap mt-3" role="region" aria-label="Recent deliveries" tabindex="0">
            <table class="table">
                <thead><tr><th scope="col">When (IST)</th><th scope="col">Channel</th><th scope="col">Provider</th><th scope="col">To</th><th scope="col">Purpose</th><th scope="col">Result</th></tr></thead>
                <tbody>
                @forelse ($deliveries as $d)
                    <tr>
                        <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($d->created_at, 'UTC')->tz('Asia/Kolkata')->format('d M, H:i:s') }}</td>
                        <td>{{ strtoupper($d->channel) }}</td><td>{{ $d->provider }}</td><td>{{ $d->to_masked }}</td><td>{{ $d->purpose }}</td>
                        <td>@if ($d->status === 'accepted')<span class="badge-success">Accepted</span>@else<span class="badge-danger">Failed</span><br><span class="text-xs text-ink-600">{{ $d->error }}</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-ink-600">Nothing sent yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>
