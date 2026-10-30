<?php

namespace App\Http\Controllers\Web;

use App\Domain\Legal\LegalDocuments;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Public legal pages from Admin › Legal pages, with their version history. */
class LegalController extends Controller
{
    public function __construct(private LegalDocuments $legal) {}

    public function show(Request $request, string $kind): View
    {
        abort_unless(isset(LegalDocuments::KINDS[$kind]), 404);
        $version = $request->query('version');
        $doc = is_string($version) ? $this->legal->version($kind, $version, app()->getLocale()) ?? $this->legal->version($kind, $version) : $this->legal->current($kind);
        // privacy and terms always exist; the other pages appear once published
        abort_if(! $doc && ! in_array($kind, ['privacy', 'terms'], true), 404);

        return view('public.legal', ['kind' => $kind, 'doc' => $doc, 'html' => $doc ? $this->legal->render($doc->body) : null, 'old' => $doc && $doc->status === 'superseded',
            'hasHistory' => $this->legal->history($kind)->count() > 1]);
    }

    public function history(string $kind): View
    {
        abort_unless(isset(LegalDocuments::KINDS[$kind]), 404);
        $versions = $this->legal->history($kind);
        abort_if($versions->isEmpty(), 404);

        return view('public.legal-history', ['kind' => $kind, 'versions' => $versions]);
    }
}
