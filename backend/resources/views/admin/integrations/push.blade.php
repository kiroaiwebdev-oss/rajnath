<x-layouts.admin title="Push notifications">
    <p class="max-w-3xl text-sm text-ink-600">Customers get every notice by email and in their account inbox. Push notifications to the Android app also need your Firebase project. The service-account key is stored encrypted and is never shown again. Saving asks you to confirm your password and authenticator code.</p>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_24rem]">
        <section class="card min-w-0 p-5 sm:p-6" aria-labelledby="fcm-h">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="fcm-h" class="text-lg">Firebase Cloud Messaging</h2>
                @if ($client)<span class="badge-success">Active · project {{ $account['project_id'] }}</span>
                @elseif ($account)<span class="badge-danger">Android app details missing</span>
                @else<span class="badge-danger">Not configured</span>@endif
            </div>
            <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm text-ink-700">
                <li>In the Firebase console, open your project and add an <strong>Android app</strong> with package name <code>com.astrorajnathjha.app</code>.</li>
                <li>Project settings › General › Your apps: copy the <strong>API key</strong>, <strong>App ID</strong> and <strong>Project number</strong> (sender ID).</li>
                <li>Project settings › Service accounts › <strong>Generate new private key</strong>, then open the downloaded file and paste all of it below.</li>
                <li>No app rebuild is needed: the app reads these settings when it starts.</li>
            </ol>
            <form method="POST" action="{{ route('admin.push.save') }}" class="mt-5 space-y-5">
                @csrf @method('PUT')
                <div>
                    <label for="f-service_account" class="field-label">Service-account JSON @if ($account)<span class="font-normal text-ink-600">(saved: {{ $account['client_email'] }}. Leave blank to keep it)</span>@endif</label>
                    <textarea id="f-service_account" name="service_account" rows="5" class="input font-mono text-xs" autocomplete="off" spellcheck="false" @error('service_account') aria-invalid="true" aria-describedby="e-sa" @enderror></textarea>
                    @error('service_account')<p id="e-sa" class="field-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-5 sm:grid-cols-3">
                    <x-field name="api_key" label="Android API key" :value="$values['api_key']" hint="Starts with AIza" />
                    <x-field name="app_id" label="Android App ID" :value="$values['app_id']" hint="1:…:android:…" />
                    <x-field name="sender_id" label="Sender ID (project number)" :value="$values['sender_id']" />
                </div>
                <button class="btn-primary">Save Firebase settings</button>
            </form>
            <form method="POST" action="{{ route('admin.push.test') }}" class="mt-6 border-t border-ink-200/60 pt-5">
                @csrf
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex-1"><x-field name="token" label="Device token to check (optional)" hint="Leave empty to only check the credentials" /></div>
                    <button class="btn-outline">Check credentials</button>
                </div>
            </form>
        </section>
        <aside class="card h-fit p-5 text-sm">
            <h2 class="text-base">Delivery</h2>
            <p class="mt-1 text-ink-600">{{ $devices }} registered app {{ \Illuminate\Support\Str::plural('device', $devices) }}.</p>
            <ul class="mt-3 divide-y divide-ink-200/60">
                @forelse ($recent as $n)
                    @php $ch = json_decode($n->channels ?? '{}', true) ?: []; @endphp
                    <li class="py-2"><span class="font-medium">{{ $n->title }}</span> <span class="text-ink-600">· {{ $n->kind }}</span><br>
                        <span class="text-xs text-ink-600">email: {{ $ch['email'] ?? 'queued' }} · push: {{ $ch['push'] ?? 'queued' }} · {{ \Illuminate\Support\Carbon::parse($n->created_at)->diffForHumans() }}</span></li>
                @empty
                    <li class="py-2 text-ink-600">No notices sent yet.</li>
                @endforelse
            </ul>
        </aside>
    </div>
</x-layouts.admin>
