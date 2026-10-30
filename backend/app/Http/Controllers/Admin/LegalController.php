<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Legal\LegalDocuments;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin › Legal pages. Anyone with legal.manage edits a draft; publishing (and taking a page
 * down) needs a fresh password + authenticator confirmation and is audited.
 */
class LegalController extends Controller
{
    public function __construct(private LegalDocuments $legal, private AuditLogger $audit) {}

    private function check(string $kind, string $locale): void
    {
        abort_unless(isset(LegalDocuments::KINDS[$kind], LegalDocuments::LOCALES[$locale]), 404);
    }

    public function index(): View
    {
        $this->authorize('legal.manage');
        $rows = DB::table('legal_documents')->whereIn('status', ['published', 'draft'])->get(['kind', 'locale', 'status', 'version', 'updated_at', 'published_at']);

        return view('admin.legal.index', ['rows' => $rows]);
    }

    public function edit(Request $request, string $kind, string $locale): View
    {
        $this->authorize('legal.manage');
        $this->check($kind, $locale);
        $draft = $this->legal->draft($kind, $locale, $request->user()->id);

        return view('admin.legal.edit', ['kind' => $kind, 'locale' => $locale, 'draft' => $draft, 'preview' => $this->legal->render($draft->body),
            'current' => DB::table('legal_documents')->where('kind', $kind)->where('locale', $locale)->where('status', 'published')->first(),
            'history' => $this->legal->history($kind, $locale)]);
    }

    public function save(Request $request, string $kind, string $locale): RedirectResponse
    {
        $this->authorize('legal.manage');
        $this->check($kind, $locale);
        $d = $request->validate(['title' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:100000'], 'change_note' => ['nullable', 'string', 'max:500']]);
        $draft = $this->legal->draft($kind, $locale, $request->user()->id);
        $this->legal->saveDraft($draft, $d['title'], $d['body'], $d['change_note'] ?? null);
        $this->audit->record('legal.draft_saved', null, ['kind' => $kind, 'locale' => $locale]);

        return redirect()->route('admin.legal.edit', [$kind, $locale])->with('status', 'Draft saved. It is not public until you publish it.');
    }

    public function publish(Request $request, string $kind, string $locale): RedirectResponse
    {
        $this->authorize('legal.manage');
        $this->check($kind, $locale);
        $d = $request->validate(['effective_on' => ['nullable', 'date', 'after_or_equal:today'], 'confirm' => ['accepted']],
            ['confirm.accepted' => 'Tick the box to confirm the text has been checked.']);
        $draft = DB::table('legal_documents')->where('kind', $kind)->where('locale', $locale)->where('status', 'draft')->first();
        abort_unless($draft, 404);
        $doc = $this->legal->publish($draft, $request->user()->id, $d['effective_on'] ?? null);

        return redirect()->route('admin.legal.edit', [$kind, $locale])->with('status', 'Published as version '.$doc->version.'. New sign-ups and orders record this version.');
    }

    public function discard(string $kind, string $locale): RedirectResponse
    {
        $this->authorize('legal.manage');
        $this->check($kind, $locale);
        $draft = DB::table('legal_documents')->where('kind', $kind)->where('locale', $locale)->where('status', 'draft')->first();
        if ($draft) {
            $this->legal->discard($draft);
            $this->audit->record('legal.draft_discarded', null, ['kind' => $kind, 'locale' => $locale]);
        }

        return redirect()->route('admin.legal.index')->with('status', 'Draft discarded.');
    }

    public function unpublish(string $kind, string $locale): RedirectResponse
    {
        $this->authorize('legal.manage');
        $this->check($kind, $locale);
        if ($locale === 'en' && in_array($kind, ['privacy', 'terms'], true)) {
            return back()->withErrors(['unpublish' => 'The privacy policy and terms must always be online. Publish a new version instead.']);
        }
        $this->legal->unpublish($kind, $locale);

        return redirect()->route('admin.legal.index')->with('status', 'Taken down. It stays in the history.');
    }
}
