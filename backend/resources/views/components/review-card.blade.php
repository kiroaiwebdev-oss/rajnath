@props(['r', 'clamp' => false])
<figure {{ $attributes->merge(['class' => 'card flex h-full flex-col p-5 sm:p-6']) }}>
    <div class="flex items-center justify-between gap-3">
        <x-stars :rating="$r->rating" />
        @if ($r->verified)
            <span class="inline-flex items-center gap-1 text-xs font-medium text-success-700"><svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>{{ __('Verified purchase') }}</span>
        @endif
    </div>
    @if ($r->title)<p class="mt-3 font-semibold">{{ $r->title }}</p>@endif
    <blockquote @class(['mt-2 whitespace-pre-line text-ink-800 leading-7', 'line-clamp-6' => $clamp])>{{ $r->body }}</blockquote>
    @if ($r->reply)
        <div class="mt-4 rounded-xl bg-ivory-100 p-3 text-sm"><p class="text-xs font-semibold uppercase tracking-wide text-gold-700">{{ __('Reply from our team') }}</p><p class="mt-1 whitespace-pre-line">{{ $r->reply }}</p></div>
    @endif
    <figcaption class="mt-auto flex items-center gap-3 pt-5 text-sm">
        <span aria-hidden="true" class="grid size-10 shrink-0 place-items-center rounded-full bg-ink-900 font-semibold text-white">{{ mb_strtoupper(mb_substr($r->display_name, 0, 1)) }}</span>
        <span><span class="block font-semibold text-ink-900">{{ $r->display_name }}{{ $r->city ? ', '.$r->city : '' }}</span>
            <span class="block text-xs text-ink-600">{{ collect([$r->subject, \Illuminate\Support\Carbon::parse($r->published_at ?? $r->created_at)->translatedFormat('M Y')])->filter()->implode(' · ') }}</span></span>
    </figcaption>
</figure>
