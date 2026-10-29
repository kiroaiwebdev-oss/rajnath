<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Privacy\PrivacyService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin › Privacy requests (PV-03/04). Blocked deletion requests open a case. privacy.handle:
 * view, assign, add notes, close, export a customer's data. privacy.approve (with step-up):
 * run the deletion for the customer once nothing blocks it, and set or clear a legal hold.
 */
class PrivacyController extends Controller
{
    public function __construct(private PrivacyService $privacy, private AuditLogger $audit) {}

    private function case(string $ref): object
    {
        $c = DB::table('privacy_requests')->where('ref', $ref)->first();
        abort_unless($c, 404);

        return $c;
    }

    public function index(Request $request): View
    {
        $this->authorize('privacy.handle');
        $show = in_array($request->query('show'), ['open', 'closed'], true) ? $request->query('show') : 'open';

        return view('admin.privacy.index', ['show' => $show,
            'rows' => DB::table('privacy_requests as r')->leftJoin('users as u', 'u.id', '=', 'r.user_id')->leftJoin('users as s', 's.id', '=', 'r.assigned_to')
                ->when($show === 'open', fn ($q) => $q->whereIn('r.case_status', ['open', 'in_progress']), fn ($q) => $q->where('r.case_status', 'closed'))
                ->select('r.*', 'u.name as customer', 'u.email', 'u.phone_e164', 's.name as assignee')->orderByDesc('r.id')->paginate(30)->withQueryString(),
            'counts' => ['open' => DB::table('privacy_requests')->whereIn('case_status', ['open', 'in_progress'])->count()]]);
    }

    public function show(string $ref): View
    {
        $this->authorize('privacy.handle');
        $c = $this->case($ref);
        $u = $c->user_id ? User::find($c->user_id) : null;
        $this->audit->record('privacy.case_viewed', null, ['request' => $c->ref]);

        return view('admin.privacy.show', ['c' => $c, 'u' => $u, 'blockers' => $u && $u->status !== 'anonymized' ? $this->privacy->blockers($u) : [],
            'notes' => json_decode($c->notes ?? '[]', true) ?: [], 'staff' => DB::table('users')->where('id', $c->assigned_to)->value('name')]);
    }

    public function update(Request $request, string $ref): RedirectResponse
    {
        $this->authorize('privacy.handle');
        $c = $this->case($ref);
        $d = $request->validate(['action' => ['required', Rule::in(['assign', 'note', 'close', 'reopen'])], 'note' => ['required_if:action,note,close', 'nullable', 'string', 'max:1000']]);
        $notes = json_decode($c->notes ?? '[]', true) ?: [];
        if (! empty($d['note'])) {
            $notes[] = ['at' => now()->toIso8601ZuluString(), 'by' => $request->user()->name, 'text' => $d['note']];
        }
        $row = ['notes' => json_encode($notes), 'updated_at' => now()];
        match ($d['action']) {
            'assign' => $row += ['assigned_to' => $request->user()->id, 'case_status' => $c->case_status === 'closed' ? 'closed' : 'in_progress'],
            'close' => $row += ['case_status' => 'closed', 'closed_at' => now()],
            'reopen' => $row += ['case_status' => 'in_progress', 'closed_at' => null],
            default => null,
        };
        if ($d['action'] === 'reopen' && ! $c->user_id) {
            throw ValidationException::withMessages(['action' => 'This person was already deleted.']);
        }
        DB::table('privacy_requests')->where('id', $c->id)->update($row);
        $this->audit->record('privacy.case_'.$d['action'], null, ['request' => $c->ref]);

        return back()->with('status', ['assign' => 'Assigned to you.', 'note' => 'Note added.', 'close' => 'Case closed.', 'reopen' => 'Case reopened.'][$d['action']]);
    }

    /** Data export for a customer who asked by phone or email (sent to them by staff). */
    public function export(string $ref): JsonResponse
    {
        $this->authorize('privacy.handle');
        $c = $this->case($ref);
        $u = $c->user_id ? User::find($c->user_id) : null;
        abort_unless($u && $u->status !== 'anonymized', 404);
        $data = $this->privacy->export($u);
        $this->audit->record('privacy.exported_by_staff', null, ['request' => $c->ref]);

        return response()->json($data, 200, ['Content-Disposition' => 'attachment; filename="customer-data-'.substr($u->ref ?? (string) $u->id, -8).'.json"', 'Cache-Control' => 'no-store'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /** Runs the deletion for the customer (privacy.approve + step-up); blockers are checked again. */
    public function runDeletion(Request $request, string $ref): RedirectResponse
    {
        $this->authorize('privacy.approve');
        $c = $this->case($ref);
        $request->validate(['confirm' => ['required', 'in:DELETE']], ['confirm.in' => 'Type DELETE to confirm.']);
        $u = $c->user_id ? User::find($c->user_id) : null;
        if (! $u || $u->status === 'anonymized' || $u->isStaff()) {
            throw ValidationException::withMessages(['confirm' => 'There is no customer account to delete for this case.']);
        }
        $res = $this->privacy->delete($u, $request->user());
        if ($res->status !== 'completed') {
            throw ValidationException::withMessages(['confirm' => 'Still blocked: '.$res->blocked_reason]);
        }

        return redirect()->route('admin.privacy.index')->with('status', 'The account was deleted (anonymised). Order and payment records are kept without name or contact.');
    }

    public function hold(Request $request, string $ref): RedirectResponse
    {
        $this->authorize('privacy.approve');
        $c = $this->case($ref);
        $d = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $u = $c->user_id ? User::find($c->user_id) : null;
        abort_unless($u && $u->status !== 'anonymized', 404);
        $u->forceFill(['privacy_hold_reason' => $d['reason'] ?: null])->save();
        $this->audit->record($d['reason'] ? 'privacy.hold_set' : 'privacy.hold_cleared', null, ['request' => $c->ref]);

        return back()->with('status', $d['reason'] ? 'Legal hold set; self-service deletion is blocked.' : 'Legal hold cleared.');
    }
}
