<x-layouts.account title="Palmistry report">
    <div class="flex flex-wrap items-center gap-3"><p class="text-sm text-ink-600">Reference {{ substr($r->ref, -8) }}</p>@include('account.palm._status', ['s' => $r->status])</div>

    @if (in_array($r->status, ['awaiting_photos', 'needs_new_photos']))
        @if ($r->status === 'needs_new_photos' && $r->staff_note)<div class="card mt-5 border-red-200 bg-red-50 p-4 text-sm" role="alert"><strong>Note from our team:</strong> {{ $r->staff_note }}</div>@endif
        <div class="mt-5 grid gap-6 lg:grid-cols-[1fr_20rem]">
            <form method="POST" action="{{ route('account.palm.upload', $r->ref) }}" enctype="multipart/form-data" class="card space-y-5 p-5 sm:p-6" novalidate>
                @csrf
                <h2 class="text-lg">Upload photos of both palms</h2>
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach (['left' => 'Left palm', 'right' => 'Right palm'] as $k => $l)
                        <div>
                            <label for="f-{{ $k }}" class="field-label">{{ $l }}</label>
                            <input id="f-{{ $k }}" name="{{ $k }}" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" required class="input file:mr-3 file:rounded-full file:border-0 file:bg-ivory-100 file:px-3 file:py-1.5" @error($k) aria-invalid="true" aria-describedby="e-{{ $k }}" @enderror>
                            @error($k)<p id="e-{{ $k }}" class="field-error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-select name="dominant_hand" label="Hand you write with" :value="$r->dominant_hand ?? 'right'" :options="['right' => 'Right', 'left' => 'Left']" />
                    <x-field name="age" label="Age (optional)" type="number" min="1" max="120" :value="$r->age" />
                    <x-select name="language" label="Report language" :value="$r->report_language" :options="['en' => 'English', 'hi' => 'Hindi']" />
                </div>
                <div><label for="f-question" class="field-label">Your main question (optional)</label><textarea id="f-question" name="question" rows="3" maxlength="1000" class="input">{{ old('question', $r->question) }}</textarea></div>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="consent" value="1" class="mt-1 size-4 accent-ink-900">
                    <span>I agree that my palm photos are used only to prepare this report. They are seen only by Acharya Ji's team and are deleted automatically after the report period.</span></label>
                @error('consent')<p class="field-error">{{ $message }}</p>@enderror
                <button class="btn-primary">Submit photos</button>
            </form>
            <aside class="card h-fit p-5 text-sm">
                <h2 class="text-base">Tips for clear photos</h2>
                <ul class="mt-3 list-disc space-y-1.5 pl-5 text-ink-700">
                    <li>Use daylight or bright light; no flash glare.</li>
                    <li>Open your palm fully, fingers slightly apart.</li>
                    <li>Hold the phone straight above the palm; fill the photo with the whole hand including the wrist.</li>
                    <li>Make sure the lines are sharp, not blurred.</li>
                </ul>
                <p class="mt-3 text-xs text-ink-600">Location data and other hidden information in photos are removed when you upload.</p>
            </aside>
        </div>
    @elseif ($r->status !== 'completed')
        <div class="card mt-5 p-6"><p>Thank you — we have your photos. Acharya Ji's team is preparing your report and will email you when it is ready.</p>
            @if ($r->left_photo)<div class="mt-4 flex gap-3">@foreach (['left', 'right'] as $s)<img src="{{ route('palm.photo', [$r->ref, $s]) }}" alt="Your {{ $s }} palm" class="h-40 rounded-xl object-cover">@endforeach</div>@endif</div>
    @else
        <article class="card mt-5 p-6">
            <h2 class="text-xl">Your palmistry report</h2>
            <p class="text-sm text-ink-600">Prepared by Acharya Dr. Rajnath Jha's team · {{ \Illuminate\Support\Carbon::parse($r->completed_at)->tz(auth()->user()->display_timezone)->format('d M Y') }}</p>
            @foreach (\App\Domain\Palmistry\PalmReadings::SECTIONS as $k => $title)
                @if (! empty($report[$k]))<section class="mt-6"><h3 class="text-base">{{ $title }}</h3><div class="mt-2 space-y-3 leading-7">@foreach (preg_split('/\R{2,}/', $report[$k]) as $para)<p>{{ $para }}</p>@endforeach</div></section>@endif
            @endforeach
            <p class="mt-8 rounded-xl bg-ivory-100 p-3 text-xs">Palmistry is a traditional interpretive practice. It is not a medical, legal or financial assessment.</p>
            <p class="mt-4 text-sm">To save a copy, use your browser's <strong>Print → Save as PDF</strong>.</p>
        </article>
    @endif
</x-layouts.account>
