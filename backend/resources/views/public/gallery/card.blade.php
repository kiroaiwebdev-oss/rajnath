@php use App\Domain\Showcase\Gallery; use App\Domain\Showcase\Profile; $c = $a->cover ?? null; @endphp
<li class="card relative overflow-hidden">
    @if ($c && $c->kind === 'photo')
        <x-photo :src="$c->thumb_path ?: $c->path" :alt="Profile::tr($c, 'caption') ?: Profile::tr($a, 'title')" class="aspect-[4/3] w-full object-cover" />
    @elseif ($c)
        <img src="{{ Gallery::videoThumb($c->video_id) }}" alt="" loading="lazy" decoding="async" width="480" height="360" class="aspect-[4/3] w-full object-cover">
    @else
        <div class="aspect-[4/3] w-full bg-ivory-100" aria-hidden="true"></div>
    @endif
    <div class="p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-gold-700">{{ __(Gallery::CATEGORIES[$a->category] ?? $a->category) }}</p>
        <h3 class="mt-1 text-base"><a href="{{ route('gallery.show', $a->slug) }}" class="no-underline after:absolute after:inset-0">{{ Profile::tr($a, 'title') }}</a></h3>
        <p class="mt-1 text-xs text-ink-600">
            {{ collect([$a->event_date ? \Illuminate\Support\Carbon::parse($a->event_date)->translatedFormat('j M Y') : null, $a->location,
                ($a->photo_count ?? 0) ? trans_choice(':count photo|:count photos', $a->photo_count, ['count' => $a->photo_count]) : null,
                ($a->video_count ?? 0) ? trans_choice(':count video|:count videos', $a->video_count, ['count' => $a->video_count]) : null])->filter()->implode(' · ') }}
        </p>
    </div>
</li>
