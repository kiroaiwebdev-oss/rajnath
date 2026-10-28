<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function index(): View
    {
        $this->authorize('content.author');

        return view('admin.faqs.index', ['faqs' => DB::table('faqs')->orderBy('sort')->orderBy('id')->get()]);
    }

    public function save(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('content.publish');
        $d = $request->validate([
            'id' => ['nullable', 'integer', 'exists:faqs,id'],
            'question' => ['required', 'string', 'max:300'],
            'answer' => ['required', 'string', 'max:3000'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
            'published' => ['nullable', 'boolean'],
        ]);
        $row = ['question' => $d['question'], 'answer' => $d['answer'], 'sort' => (int) ($d['sort'] ?? 0), 'published' => (bool) ($d['published'] ?? false), 'updated_at' => now()];
        if ($d['id'] ?? null) {
            DB::table('faqs')->where('id', $d['id'])->update($row);
        } else {
            $d['id'] = DB::table('faqs')->insertGetId($row + ['locale' => 'en', 'created_at' => now()]);
        }
        $audit->record('content.faq_saved', null, ['faq' => $d['id'], 'published' => $row['published']]);

        return back()->with('status', 'FAQ saved.');
    }

    public function destroy(int $id, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('content.publish');
        DB::table('faqs')->where('id', $id)->delete();
        $audit->record('content.faq_deleted', null, ['faq' => $id]);

        return back()->with('status', 'FAQ deleted.');
    }
}
