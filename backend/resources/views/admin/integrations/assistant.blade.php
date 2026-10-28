<x-layouts.admin title="AI assistant">
    <p class="max-w-3xl text-sm text-ink-600">The assistant answers questions about your services, prices, booking and policies, using only your catalogue, FAQs and the notes below. It hands customers over to your team when it is unsure. If one provider fails, the next one in your order is tried. Keys are stored encrypted.</p>
    <p class="mt-2 text-sm">Status:
        @if ($enabled && $active)<span class="badge-success">On · {{ collect($active)->map(fn ($p) => $providers[$p]['label'])->implode(' → ') }}</span>
        @else<span class="badge-danger">Off</span>@endif
    </p>

    @if ($canConfigure)
        <form method="POST" action="{{ route('admin.assistant.save') }}" class="card mt-6 space-y-5 p-5 sm:p-6" aria-labelledby="ai-h">
            @csrf @method('PUT')
            <h2 id="ai-h" class="text-lg">Providers</h2>
            <label class="flex items-center gap-2 text-sm"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" class="size-4 accent-ink-900" @checked($enabled)> Assistant switched on (website and app)</label>
            @error('provider')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            <div class="grid gap-5 lg:grid-cols-2">
                @foreach ($providers as $k => $p)
                    <fieldset class="rounded-2xl border border-ink-200 p-4">
                        <legend class="px-1 text-sm font-semibold">{{ $p['label'] }} @if ($p['configured'])<span class="badge-success ml-1">ready</span>@endif</legend>
                        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="{{ $k }}_enabled" value="0"><input type="checkbox" name="{{ $k }}_enabled" value="1" class="size-4 accent-ink-900" @checked($p['enabled'])> Use {{ $p['label'] }}</label>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <x-field :name="$k.'_key'" label="API key" type="password" :hint="($p['key_masked'] ? 'Saved: '.$p['key_masked'].'. ' : '').'Leave blank to keep.'" />
                            <x-field :name="$k.'_model'" label="Model" :value="$p['current_model']" :hint="$p['model'] ? 'Default: '.$p['model'] : 'Required'" />
                            @if ($k === 'cloudflare')
                                <x-field name="cloudflare_account_id" label="Cloudflare account ID" :value="$cfAccount" />
                            @endif
                        </div>
                        <p class="mt-2 text-xs text-ink-600">Where to get it: {{ $p['keys'] }}</p>
                    </fieldset>
                @endforeach
            </div>
            <fieldset>
                <legend class="field-label">Order to try (first is used; the next ones are fallbacks)</legend>
                <div class="mt-2 grid gap-3 sm:grid-cols-4">
                    @php $ord = array_values(array_filter(explode(',', $order))); @endphp
                    @for ($i = 0; $i < 4; $i++)
                        <x-select :name="'order['.$i.']'" :label="($i + 1).'.'" :options="collect($providers)->map(fn ($p) => $p['label'])->all()" :value="$ord[$i] ?? array_keys($providers)[$i]" />
                    @endfor
                </div>
            </fieldset>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="daily_user" label="Questions per signed-in customer per day" type="number" :value="$limits['user']" />
                <x-field name="daily_guest" label="Questions per visitor (not signed in) per day" type="number" :value="$limits['guest']" hint="0 = sign-in required" />
            </div>
            <button class="btn-primary">Save AI settings</button>
        </form>
        <section class="card mt-6 p-5" aria-labelledby="test-h">
            <h2 id="test-h" class="text-base">Test a provider</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($providers as $k => $p)
                    <form method="POST" action="{{ route('admin.assistant.test') }}">@csrf<input type="hidden" name="provider" value="{{ $k }}"><button class="btn-outline" @disabled(! $p['configured'])>Test {{ $p['label'] }}</button></form>
                @endforeach
            </div>
        </section>
    @endif

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="card min-w-0 p-5" aria-labelledby="kn-h">
            <h2 id="kn-h" class="text-lg">Knowledge notes</h2>
            <p class="text-sm text-ink-600">Facts the assistant may use: office timings, how puja is performed, language options, delivery areas… Do not add customer data.</p>
            <form method="POST" action="{{ route('admin.assistant.knowledge') }}" class="mt-4 space-y-3">
                @csrf
                <x-field name="title" label="Title" />
                <div><label for="f-body" class="field-label">Text</label><textarea id="f-body" name="body" rows="4" maxlength="4000" class="input"></textarea>@error('body')<p class="field-error">{{ $message }}</p>@enderror</div>
                <label class="flex items-center gap-2 text-sm"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" class="size-4 accent-ink-900" checked> Use this note</label>
                <button class="btn-primary">Add note</button>
            </form>
            <ul class="mt-5 divide-y divide-ink-200/60">
                @foreach ($knowledge as $k)
                    <li class="py-3">
                        <details>
                            <summary class="cursor-pointer font-medium">{{ $k->title }} @unless ($k->active)<span class="badge-ink">off</span>@endunless</summary>
                            <form method="POST" action="{{ route('admin.assistant.knowledge') }}" class="mt-3 space-y-3">
                                @csrf <input type="hidden" name="id" value="{{ $k->id }}">
                                <x-field name="title" :id="'kt-'.$k->id" label="Title" :value="$k->title" />
                                <div><label for="kb-{{ $k->id }}" class="field-label">Text</label><textarea id="kb-{{ $k->id }}" name="body" rows="4" maxlength="4000" class="input">{{ $k->body }}</textarea></div>
                                <label class="flex items-center gap-2 text-sm"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" class="size-4 accent-ink-900" @checked($k->active)> Use this note</label>
                                <button class="btn-outline">Save</button>
                            </form>
                        </details>
                    </li>
                @endforeach
            </ul>
        </section>
        <section class="card min-w-0 p-5" aria-labelledby="cv-h">
            <h2 id="cv-h" class="text-lg">Recent conversations</h2>
            <p class="text-sm text-ink-600">Ones that asked for a person (or mentioned self-harm) are listed first. Conversations are deleted after 90 days.</p>
            <ul class="mt-3 divide-y divide-ink-200/60 text-sm">
                @forelse ($conversations as $c)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <a href="{{ route('admin.assistant.conversation', $c->ref) }}">{{ $c->customer ?? 'Visitor' }} · {{ $c->messages }} messages · {{ $c->channel }}</a>
                        <span>@if ($c->handoff)<span class="badge-danger">needs a person</span>@endif <span class="text-ink-600">{{ \Illuminate\Support\Carbon::parse($c->updated_at)->diffForHumans() }}</span></span>
                    </li>
                @empty
                    <li class="py-2 text-ink-600">No conversations yet.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-layouts.admin>
