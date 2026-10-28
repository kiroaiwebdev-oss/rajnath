<x-layouts.account title="Choose an astrologer">
    <section class="card p-5" aria-labelledby="ch-h">
        <h2 id="ch-h" class="text-lg">{{ $o->t('en')->title }} — {{ $v->name }}</h2>
        @if ($list->isEmpty())
            <p class="mt-4 rounded-xl bg-ivory-100 p-4 text-sm">No astrologer is available for this service right now. Please <a href="{{ route('contact') }}">contact us</a>.</p>
        @else
            <ul class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach ($list as $p)
                    @php $a = \App\Domain\Practitioners\Practitioners::toApi($p); @endphp
                    <li class="rounded-2xl border border-ink-200 p-4">
                        <div class="flex items-center gap-3">
                            @if ($a['photo_url'])<img src="{{ $a['photo_url'] }}" alt="" class="size-14 rounded-full object-cover">@endif
                            <div><p class="font-semibold">{{ $p->display_name }}</p>
                                <p class="text-xs text-ink-600">{{ implode(', ', $a['languages']) }}@if ($p->experience_years) · {{ $p->experience_years }} years @endif</p></div>
                        </div>
                        <p class="mt-2 line-clamp-4 text-sm text-ink-700">{{ $p->bio }}</p>
                        <a class="btn-primary mt-3" href="{{ route('account.book', [$v->ref, 'practitioner' => $p->ref]) }}">See times with {{ $p->display_name }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.account>
