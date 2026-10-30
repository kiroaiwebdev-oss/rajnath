<x-layouts.account :title="$review ? 'Edit your review' : 'Write a review'">
    <form method="POST" action="{{ $review ? route('account.reviews.update', $review->ref) : route('account.reviews.store', $order->ref) }}" class="card max-w-2xl space-y-5 p-5 sm:p-6">
        @csrf @if ($review) @method('PUT') @endif
        <p class="text-sm text-ink-600">About: <strong class="text-ink-900">{{ $label }}</strong></p>
        <fieldset>
            <legend class="field-label">Your rating</legend>
            <div class="flex flex-row-reverse justify-end gap-1">
                @for ($i = 5; $i >= 1; $i--)
                    <input type="radio" id="star-{{ $i }}" name="rating" value="{{ $i }}" class="peer sr-only" @checked((int) old('rating', $review->rating ?? 5) === $i) required>
                    <label for="star-{{ $i }}" class="cursor-pointer text-ink-200 transition hover:text-gold-500 peer-checked:text-gold-500 peer-focus-visible:outline-2" title="{{ $i }} {{ $i === 1 ? 'star' : 'stars' }}">
                        <span class="sr-only">{{ $i }} {{ $i === 1 ? 'star' : 'stars' }}</span>
                        <svg aria-hidden="true" class="size-9" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.9l-5.2 2.7 1-5.8L1.5 7.7l5.9-.9z"/></svg>
                    </label>
                @endfor
            </div>
            @error('rating')<p class="field-error">{{ $message }}</p>@enderror
        </fieldset>
        <x-field name="title" label="Title (optional)" :value="$review?->title" maxlength="120" placeholder="Clear and practical guidance" />
        <div>
            <label for="f-body" class="field-label">Your experience</label>
            <textarea id="f-body" name="body" rows="6" minlength="20" maxlength="2000" required class="input" aria-describedby="body-hint">{{ old('body', $review?->body) }}</textarea>
            <p id="body-hint" class="field-hint">At least 20 characters. Please do not include phone numbers, birth details or other private information.</p>
            @error('body')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="display_name" label="Name to show" :value="$review?->display_name ?? $suggested" required maxlength="80" hint="For example your first name and initial." />
            <x-field name="city" label="City (optional)" :value="$review?->city" maxlength="80" />
        </div>
        @unless ($review)
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="consent" value="1" class="mt-0.5 size-4 accent-ink-900" @checked(old('consent'))> I agree that {{ $site('brand.name') }} may publish this review with the name and city above on its website and app. I can delete it at any time.</label>
            @error('consent')<p class="field-error">{{ $message }}</p>@enderror
        @endunless
        @error('order')<p class="field-error">{{ $message }}</p>@enderror
        <div class="flex flex-wrap gap-3"><button class="btn-primary">{{ $review ? 'Save and send again' : 'Send review' }}</button><a href="{{ route('account.reviews.index') }}" class="btn-ghost">Cancel</a></div>
    </form>
</x-layouts.account>
