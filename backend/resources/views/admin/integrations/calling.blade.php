<x-layouts.admin title="Calling">
    @php $badge = fn ($p) => $ready[$p] ? '<span class="badge-success">ready</span>' : '<span class="badge-ink">not set up</span>'; @endphp
    <p class="max-w-3xl text-sm text-ink-600">Choose how audio/video consultations happen. When a booking is paid, a link is created with the default provider and sent to the customer. You can switch any booking to in-app, Zoom or Google Meet, or paste your own link, from Bookings. Secrets are stored encrypted. Saving asks for your password and authenticator code.</p>
    @error('provider')<p class="field-error mt-3" role="alert">{{ $message }}</p>@enderror
    @if (session('google_url'))<p class="card mt-4 p-4 text-sm" role="status">Next: <a class="btn-primary ml-2" href="{{ session('google_url') }}">Continue to Google and allow calendar access</a></p>@endif

    <form method="POST" action="{{ route('admin.calling.update') }}" class="mt-6 space-y-6">
        @csrf @method('PUT')
        <section class="card p-5 sm:p-6" aria-labelledby="df-h">
            <h2 id="df-h" class="text-lg">Default for new bookings</h2>
            <div class="mt-3 max-w-md"><x-select name="default" label="Provider" :options="\App\Domain\Meetings\Meetings::PROVIDERS" :value="$default" hint="If the default is not set up, the in-app call is used when available; otherwise staff add a link." /></div>
        </section>

        <div class="grid gap-6 xl:grid-cols-3">
            <section class="card min-w-0 space-y-4 p-5" aria-labelledby="lk-h">
                <h2 id="lk-h" class="text-lg">In-app call (LiveKit) {!! $badge('livekit') !!}</h2>
                <p class="text-sm text-ink-600">Calls inside the website and the Android app, on your own VPS. Setup: <code>docs/ops/livekit-on-vps.md</code>.</p>
                <x-field name="livekit_url" label="Server URL" :value="$lk['url']" hint="wss://rtc.your-domain.com" />
                <x-field name="livekit_key" label="API key" :value="$lk['key']" />
                <x-field name="livekit_secret" label="API secret" type="password" :hint="($lk['secret'] ? 'Saved: '.$lk['secret'].'. ' : '').'Leave blank to keep.'" />
            </section>
            <section class="card min-w-0 space-y-4 p-5" aria-labelledby="zm-h">
                <h2 id="zm-h" class="text-lg">Zoom {!! $badge('zoom') !!}</h2>
                <p class="text-sm text-ink-600">marketplace.zoom.us › Develop › Build App › <strong>Server-to-Server OAuth</strong>. Add the scope <code>meeting:write:meeting:admin</code> (create, update, delete meetings) and activate the app.</p>
                <x-field name="zoom_account_id" label="Account ID" :value="$zoom['account_id']" />
                <x-field name="zoom_client_id" label="Client ID" :value="$zoom['client_id']" />
                <x-field name="zoom_client_secret" label="Client secret" type="password" :hint="($zoom['secret'] ? 'Saved: '.$zoom['secret'].'. ' : '').'Leave blank to keep.'" />
            </section>
            <section class="card min-w-0 space-y-4 p-5" aria-labelledby="gm-h">
                <h2 id="gm-h" class="text-lg">Google Meet {!! $badge('google_meet') !!}</h2>
                <p class="text-sm text-ink-600">console.cloud.google.com › enable <strong>Google Calendar API</strong> › Credentials › OAuth client (Web). Authorised redirect URI:</p>
                <code class="block break-all rounded-lg bg-ivory-100 p-2 text-xs">{{ $google['redirect'] }}</code>
                <x-field name="google_client_id" label="Client ID" :value="$google['client_id']" />
                <x-field name="google_client_secret" label="Client secret" type="password" :hint="($google['secret'] ? 'Saved: '.$google['secret'].'. ' : '').'Leave blank to keep.'" />
                <x-field name="google_calendar" label="Calendar" :value="$google['calendar']" hint="primary, or a calendar ID" />
                <p class="text-sm">{{ $google['connected'] ? 'Google account connected.' : 'Not connected yet — save, then press “Connect Google account”.' }}</p>
            </section>
        </div>
        <button class="btn-primary">Save calling settings</button>
    </form>

    <section class="card mt-6 p-5" aria-labelledby="ts-h">
        <h2 id="ts-h" class="text-base">Check the connection</h2>
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach (['livekit' => 'Test LiveKit', 'zoom' => 'Test Zoom', 'google_meet' => 'Test Google'] as $p => $label)
                <form method="POST" action="{{ route('admin.calling.test') }}">@csrf<input type="hidden" name="provider" value="{{ $p }}"><button class="btn-outline" @disabled(! $ready[$p])>{{ $label }}</button></form>
            @endforeach
            <form method="POST" action="{{ route('admin.calling.google.connect') }}">@csrf<button class="btn-outline" @disabled(! $google['client_id'])>{{ $google['connected'] ? 'Reconnect Google account' : 'Connect Google account' }}</button></form>
        </div>
    </section>
</x-layouts.admin>
