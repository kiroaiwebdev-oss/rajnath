<x-layouts.account title="Privacy & your data">
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card p-5 sm:p-6" aria-labelledby="ex-h">
            <h2 id="ex-h" class="text-lg">Download your data</h2>
            <p class="mt-2 text-sm text-ink-600">A file with your account details, birth profiles, orders, bookings, reports, puja details and support messages.</p>
            @unless ($fresh)<p class="mt-2 text-xs text-ink-600">For your security you will be asked to sign in again first.</p>@endunless
            <form method="POST" action="{{ route('account.privacy.export') }}" class="mt-4">@csrf<button class="btn-outline">Download my data (JSON)</button></form>
        </section>
        <section class="card border-red-200 p-5 sm:p-6" aria-labelledby="del-h">
            <h2 id="del-h" class="text-lg text-danger-700">Delete my account</h2>
            <p class="mt-2 text-sm">This permanently erases your name, email, mobile, birth profiles, palm and puja photos, and report and message contents, then signs you out everywhere. Order amounts and dates are kept without your name because tax law requires it.</p>
            @if ($blockers)
                <div class="mt-4 rounded-xl bg-gold-50 p-3 text-sm" role="note"><p class="font-semibold">Not possible yet:</p><ul class="mt-1 list-disc pl-5">@foreach ($blockers as $b)<li>{{ $b }}</li>@endforeach</ul></div>
            @else
                <form method="POST" action="{{ route('account.privacy.delete') }}" class="mt-4 space-y-3">
                    @csrf
                    <x-field name="confirm" label="Type DELETE to confirm" autocomplete="off" required />
                    @unless ($fresh)<p class="text-xs text-ink-600">You will be asked to sign in again first.</p>@endunless
                    <button class="btn-danger">Delete my account permanently</button>
                </form>
            @endif
        </section>
    </div>
</x-layouts.account>
