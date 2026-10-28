<?php

namespace App\Http\Controllers\Web;

use App\Domain\Privacy\PrivacyService;
use App\Domain\Puja\PujaBookings;
use App\Domain\Reports\ReportJobs;
use App\Domain\Support\Tickets;
use App\Http\Controllers\Controller;
use App\Models\BirthProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Customer-side puja bookings, reports, support tickets and privacy (export / delete). */
class CustomerOpsController extends Controller
{
    private function own(string $table, string $ref, Request $r): object
    {
        $row = DB::table($table)->where('ref', $ref)->where('user_id', $r->user()->id)->first();
        abort_unless($row, 404);

        return $row;
    }

    // ---------- Puja ----------
    public function pujaIndex(Request $r): View
    {
        return view('account.puja.index', ['rows' => DB::table('puja_bookings as p')->join('offering_translations as t', fn ($j) => $j->on('t.offering_id', '=', 'p.offering_id')->where('t.locale', 'en'))
            ->where('p.user_id', $r->user()->id)->select('p.*', 't.title')->orderByDesc('p.id')->get()]);
    }

    public function pujaShow(Request $r, string $ref): View
    {
        $b = $this->own('puja_bookings', $ref, $r);

        return view('account.puja.show', ['b' => $b, 'title' => DB::table('offering_translations')->where('offering_id', $b->offering_id)->where('locale', 'en')->value('title'),
            'sankalp' => PujaBookings::sankalp($b), 'evidence' => $b->evidence ? json_decode($b->evidence, true) : null]);
    }

    public function pujaDetails(Request $r, string $ref, PujaBookings $puja): RedirectResponse
    {
        $b = $this->own('puja_bookings', $ref, $r);
        $puja->submitSankalp($b, $r->user(), $r->validate(PujaBookings::sankalpRules(), ['phone.regex' => 'Enter a valid mobile number.']));

        return back()->with('status', 'Sankalp details received. We will confirm the date and priest shortly.');
    }

    /** Private puja proof photo (owner or fulfilment staff). */
    public function pujaPhoto(Request $r, string $ref, int $i): StreamedResponse
    {
        $b = DB::table('puja_bookings')->where('ref', $ref)->first();
        $u = $r->user();
        abort_unless($b && ($b->user_id === $u->id || ($u->isStaff() && $r->session()->has('staff_mfa_at') && $u->can('puja.fulfil'))), 404);
        $path = (json_decode($b->evidence ?? '{}', true)['photos'] ?? [])[$i] ?? null;
        abort_unless($path && Storage::disk(PujaBookings::DISK)->exists($path), 404);

        return Storage::disk(PujaBookings::DISK)->response($path, null, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    // ---------- Reports ----------
    public function reportIndex(Request $r): View
    {
        return view('account.reports.index', ['rows' => DB::table('report_jobs as j')->join('offerings as o', 'o.slug', '=', 'j.offering_slug')->join('offering_translations as t', fn ($x) => $x->on('t.offering_id', '=', 'o.id')->where('t.locale', 'en'))
            ->where('j.user_id', $r->user()->id)->select('j.*', 't.title')->orderByDesc('j.id')->get()]);
    }

    public function reportShow(Request $r, string $ref): View
    {
        $j = $this->own('report_jobs', $ref, $r);
        $o = DB::table('offerings')->where('slug', $j->offering_slug)->first();

        return view('account.reports.show', ['j' => $j, 'title' => DB::table('offering_translations')->where('offering_id', $o->id)->where('locale', 'en')->value('title'),
            'defs' => ReportJobs::sections($j->offering_slug), 'sections' => $j->sections ? json_decode($j->sections, true) : [], 'chart' => $j->chart ? json_decode($j->chart, true) : null,
            'subjects' => $j->subjects ? json_decode($j->subjects, true) : [], 'two' => ReportJobs::needsTwo($j->offering_slug),
            'profiles' => BirthProfile::where('user_id', $r->user()->id)->orderByDesc('is_primary')->get()]);
    }

    public function reportDetails(Request $r, string $ref, ReportJobs $jobs): RedirectResponse
    {
        $j = $this->own('report_jobs', $ref, $r);
        $two = ReportJobs::needsTwo($j->offering_slug);
        $d = $r->validate(['profile_a' => ['required', 'string', 'size:26'], 'profile_b' => [$two ? 'required' : 'nullable', 'string', 'size:26', 'different:profile_a'],
            'language' => ['required', Rule::in(['en', 'hi'])], 'question' => ['nullable', 'string', 'max:1000']]);
        $jobs->submitDetails($j, $r->user(), array_values(array_filter([$d['profile_a'], $two ? $d['profile_b'] : null])), $d['language'], $d['question'] ?? null);

        return back()->with('status', 'Details received. Your chart has been calculated and Acharya Ji\'s team is preparing your report.');
    }

    // ---------- Support ----------
    public function supportIndex(Request $r): View
    {
        return view('account.support.index', ['rows' => DB::table('tickets')->where('user_id', $r->user()->id)->orderByDesc('updated_at')->get(),
            'orders' => DB::table('orders')->where('user_id', $r->user()->id)->orderByDesc('id')->limit(20)->get(['ref', 'kind', 'snapshot', 'created_at'])]);
    }

    public function supportStore(Request $r, Tickets $t): RedirectResponse
    {
        $d = $r->validate(['category' => ['required', Rule::in(array_keys(Tickets::CATEGORIES))], 'subject' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:5000'], 'order_ref' => ['nullable', 'string', 'size:26']]);
        $ticket = $t->open($r->user(), $d);

        return redirect()->route('account.support.show', $ticket->ref)->with('status', 'Request received. We usually reply within one working day.');
    }

    public function supportShow(Request $r, string $ref): View
    {
        $t = $this->own('tickets', $ref, $r);

        return view('account.support.show', ['t' => $t, 'messages' => DB::table('ticket_messages as m')->join('users as u', 'u.id', '=', 'm.author_id')->where('m.ticket_id', $t->id)->where('m.internal', false)
            ->orderBy('m.id')->get(['m.body', 'm.created_at', 'u.kind', 'u.name'])]);
    }

    public function supportReply(Request $r, string $ref, Tickets $tickets): RedirectResponse
    {
        $t = $this->own('tickets', $ref, $r);
        $tickets->reply($t, $r->user(), $r->validate(['body' => ['required', 'string', 'max:5000']])['body']);

        return back()->with('status', 'Message sent.');
    }

    public function supportClose(Request $r, string $ref, Tickets $tickets): RedirectResponse
    {
        $tickets->setStatus($this->own('tickets', $ref, $r), $r->user(), 'closed');

        return back()->with('status', 'Request closed.');
    }

    // ---------- Privacy ----------
    private function recentlySignedIn(Request $r): bool
    {
        return now()->timestamp - (int) $r->session()->get('customer_reauth_at', 0) < 15 * 60;
    }

    public function privacy(Request $r, PrivacyService $p): View
    {
        return view('account.privacy', ['blockers' => $p->blockers($r->user()), 'fresh' => $this->recentlySignedIn($r)]);
    }

    public function export(Request $r, PrivacyService $p): JsonResponse|RedirectResponse
    {
        if (! $this->recentlySignedIn($r)) {
            return $this->reauth($r);
        }

        return response()->json($p->export($r->user()), 200, ['Content-Disposition' => 'attachment; filename="my-data-'.now()->format('Ymd').'.json"', 'Cache-Control' => 'no-store'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function destroy(Request $r, PrivacyService $p): RedirectResponse|View
    {
        if (! $this->recentlySignedIn($r)) {
            return $this->reauth($r);
        }
        $r->validate(['confirm' => ['required', 'in:DELETE']], ['confirm.in' => 'Type DELETE in capital letters to confirm.']);
        $res = $p->delete($r->user());
        if ($res->status !== 'completed') {
            return back()->withErrors(['confirm' => $res->blocked_reason]);
        }
        Auth::guard('web')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return view('public.account-deleted', ['steps' => json_decode($res->steps, true), 'ref' => $res->ref]);
    }

    private function reauth(Request $r): RedirectResponse
    {
        Auth::guard('web')->logout();
        $r->session()->invalidate();
        $r->session()->put('url.intended', route('account.privacy'));

        return redirect()->route('account.sign-in')->with('status', 'For your security, please sign in again to continue.');
    }
}
