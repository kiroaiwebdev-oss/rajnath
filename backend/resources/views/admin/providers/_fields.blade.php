{{-- Renders a group of provider fields. Secret fields never show their value; blank keeps the saved one. --}}
@foreach ($fields as $key => $f)
    @php $n = str_replace('.', '_', $key); @endphp
    <div @class(['sm:col-span-2' => in_array($key, ['sms.otp_text', 'sms.fast2sms.route', 'sms.fast2sms.variables'])])>
        @if (isset($f['options']))
            <x-select :name="$n" :label="$f['label']" :options="$f['options']" :value="$values[$key] ?? null" :hint="$f['hint']" />
        @elseif ($f['secret'])
            <x-field :name="$n" :label="$f['label']" type="password" autocomplete="new-password"
                     :hint="($masked[$key] ? 'Saved: '.$masked[$key].'. ' : 'Not saved yet. ').'Leave blank to keep it.'" />
            @if ($masked[$key])
                <label class="mt-2 flex min-h-9 items-center gap-2 text-sm text-ink-700">
                    <input type="checkbox" name="clear_{{ $n }}" value="1" class="size-4 accent-ink-900"> Remove the saved value
                </label>
            @endif
        @else
            <x-field :name="$n" :label="$f['label']" :value="$values[$key] ?? null" :hint="$f['hint']" />
        @endif
    </div>
@endforeach
