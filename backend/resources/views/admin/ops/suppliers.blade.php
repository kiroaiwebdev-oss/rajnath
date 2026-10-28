<x-layouts.admin title="Priests & temples">
    <p class="max-w-3xl text-sm text-ink-600">A puja or chadhava can be published and assigned only to a priest/temple whose signed authorisation is uploaded and still valid. Uploading the authorisation records you as the verifier.</p>
    <div class="mt-5 space-y-4">
        @foreach ($suppliers->push(null) as $s)
            <details class="card p-4" @if (! $s && $suppliers->count() <= 1) open @endif>
                <summary class="cursor-pointer font-medium">@if ($s){{ $s->name }} <span class="text-sm font-normal text-ink-600">({{ $s->kind }}, {{ $s->city }})</span>
                    @if (\App\Domain\Puja\PujaBookings::supplierOk($s))<span class="badge-success ml-1">Authorised</span>@else<span class="badge-danger ml-1">Not authorised</span>@endif @else + Add priest / temple @endif</summary>
                <form method="POST" action="{{ route('admin.ops.supplier') }}" enctype="multipart/form-data" class="mt-4 grid gap-4 sm:grid-cols-2">
                    @csrf <input type="hidden" name="id" value="{{ $s?->id }}">
                    @php $sid = $s?->id ?? 'new'; @endphp
                    <x-select name="kind" label="Type" :value="$s?->kind" :options="['priest' => 'Priest / pandit', 'temple' => 'Temple', 'vendor' => 'Samagri / prasad vendor']" :id="'k-'.$sid" />
                    <x-field name="name" label="Name" :value="$s?->name" required :id="'n-'.$sid" />
                    <x-field name="contact_name" label="Contact person" :value="$s?->contact_name" :id="'c-'.$sid" />
                    <x-field name="phone" label="Phone" :value="$s?->phone" :id="'p-'.$sid" />
                    <x-field name="city" label="City" :value="$s?->city" :id="'ci-'.$sid" />
                    <x-field name="authorized_until" label="Authorisation valid until" type="date" :value="$s?->authorized_until" :id="'au-'.$sid" />
                    <div class="sm:col-span-2"><label for="a-{{ $sid }}" class="field-label">Signed authorisation (PDF/JPG/PNG){{ $s?->authorization_doc ? ' — uploaded '.\Illuminate\Support\Carbon::parse($s->verified_at)->format('d M Y') : '' }}</label>
                        <input id="a-{{ $sid }}" name="authorization" type="file" accept="application/pdf,image/jpeg,image/png" class="input">@error('authorization')<p class="field-error">{{ $message }}</p>@enderror</div>
                    <fieldset class="sm:col-span-2"><legend class="field-label">Pujas / chadhava they perform</legend>
                        <div class="grid max-h-56 gap-1 overflow-y-auto rounded-xl border border-ink-200 p-3 sm:grid-cols-2">
                            @foreach ($offerings as $o)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="offerings[]" value="{{ $o->id }}" class="size-4 accent-ink-900" @checked($s && in_array($o->id, ($links[$s->id] ?? collect())->all()))> {{ $o->title }}</label>@endforeach
                        </div></fieldset>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" class="size-4 accent-ink-900" @checked($s?->active ?? true)> Active</label>
                    <button class="btn-primary sm:col-span-2">Save</button>
                </form>
            </details>
        @endforeach
    </div>
</x-layouts.admin>
