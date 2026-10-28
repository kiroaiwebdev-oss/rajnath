<x-layouts.account :title="$reschedule ? 'Choose a new time' : 'Choose a time'">
    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <section class="card min-w-0 p-5" aria-labelledby="slots-h">
            <h2 id="slots-h" class="text-lg">{{ $o->t('en')->title }} — {{ $v->name }}</h2>
            <p class="text-sm text-ink-600">@if ($practitioner ?? null)With {{ $practitioner->display_name }} · @endif Times are India time (IST). {{ $v->duration_min }} minutes.</p>
            @error('start')<p class="field-error mt-3" role="alert">{{ $message }}</p>@enderror
            @if (! $slots)
                <p class="mt-6 rounded-xl bg-ivory-100 p-4 text-sm">No free times in the next three weeks. Please <a href="{{ route('contact') }}">contact us</a> and we will find a time.</p>
            @else
                <form method="POST" action="{{ $reschedule ? route('account.bookings.reschedule', $reschedule->ref) : route('account.book', $v->ref) }}" class="mt-4 space-y-6">
                    @csrf
                    @unless ($reschedule)<input type="hidden" name="key" value="{{ \App\Http\Controllers\Web\PaymentController::newKey() }}">@if ($practitioner ?? null)<input type="hidden" name="practitioner" value="{{ $practitioner->ref }}">@endif @endunless
                    @foreach ($slots as $date => $list)
                        <fieldset>
                            <legend class="text-sm font-semibold">{{ \Illuminate\Support\Carbon::parse($date)->format('l, d M') }}</legend>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($list as $s)
                                    <label class="cursor-pointer rounded-full border border-ink-200 px-4 py-2 text-sm has-[:checked]:border-ink-900 has-[:checked]:bg-ink-900 has-[:checked]:text-white has-[:focus-visible]:outline-3 has-[:focus-visible]:outline-gold-500">
                                        <input type="radio" name="start" value="{{ $s['start'] }}" class="sr-only" required> {{ $s['label'] }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                    @unless ($reschedule)
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-select name="birth_profile" label="Birth details to use (optional)" :options="['' => 'None'] + $profiles->mapWithKeys(fn ($p) => [$p->ref => $p->name])->all()" />
                        </div>
                        @if (($wallet ?? 0) > 0)
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="use_wallet" value="1" class="size-4 accent-ink-900"> Use wallet balance first ({{ \App\Support\Money::format($wallet) }} available)</label>
                        @endif
                        <div><label for="f-topic" class="field-label">What would you like to discuss? (optional)</label><textarea id="f-topic" name="topic" rows="3" maxlength="1000" class="input"></textarea></div>
                    @endunless
                    <button class="btn-primary">{{ $reschedule ? 'Move booking' : 'Continue to pay '.\App\Support\Money::format($v->price_minor) }}</button>
                </form>
            @endif
        </section>
        <aside class="card h-fit p-5 text-sm">
            <h2 class="text-base">Booking policy</h2>
            <ul class="mt-2 list-disc space-y-1.5 pl-5 text-ink-700">
                <li>Your time is held for {{ config('booking.hold_minutes') }} minutes while you pay.</li>
                <li>Cancel or move it online up to {{ config('booking.change_hours') }} hours before the start; cancellations are refunded in full to your wallet.</li>
                <li>You can move a booking {{ config('booking.max_reschedules') }} time online; contact us for anything else.</li>
                <li>We send the call link (or confirm the office visit) before the session.</li>
            </ul>
        </aside>
    </div>
</x-layouts.account>
