@php use App\Domain\Legal\LegalDocuments; @endphp
<x-layouts.admin title="Legal pages">
    <p class="max-w-3xl text-sm text-ink-600">Every published version is kept and dated. Sign-ups and orders record the version the customer agreed to. Have a lawyer or CA check changes before you publish. Razorpay asks for the privacy, terms, refund and shipping pages before switching on live payments.</p>
    <div class="table-wrap mt-6" role="region" aria-label="Legal pages" tabindex="0">
        <table class="table">
            <thead><tr><th scope="col">Page</th>@foreach (LegalDocuments::LOCALES as $l)<th scope="col">{{ $l }}</th>@endforeach<th scope="col"><span class="sr-only">Public page</span></th></tr></thead>
            <tbody>
                @foreach (LegalDocuments::KINDS as $kind => [$title, $path])
                    <tr>
                        <th scope="row" class="font-medium">{{ $title }}</th>
                        @foreach (LegalDocuments::LOCALES as $loc => $l)
                            @php $pub = $rows->first(fn ($r) => $r->kind === $kind && $r->locale === $loc && $r->status === 'published'); $draft = $rows->first(fn ($r) => $r->kind === $kind && $r->locale === $loc && $r->status === 'draft'); @endphp
                            <td>
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($pub)<span class="badge-success">Live · {{ $pub->version }}</span>@else<span class="badge-ink">{{ $loc === 'en' ? 'Not published' : 'Uses English' }}</span>@endif
                                    @if ($draft)<span class="badge-gold">Draft</span>@endif
                                    <a href="{{ route('admin.legal.edit', [$kind, $loc]) }}" class="text-sm">{{ $draft ? 'Continue editing' : 'Edit' }}</a>
                                </div>
                            </td>
                        @endforeach
                        <td>@if ($rows->contains(fn ($r) => $r->kind === $kind && $r->status === 'published'))<a href="{{ url($path) }}" target="_blank" rel="noopener" class="text-sm">View</a>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.admin>
