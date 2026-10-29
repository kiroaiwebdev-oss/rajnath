<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Practitioners\Applications;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin › Practitioners › Applications: accept (sends a practitioner invitation) or reject. */
class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('practitioners.view');
        $status = in_array($request->query('status'), ['submitted', 'accepted', 'rejected', 'withdrawn'], true) ? $request->query('status') : 'submitted';

        return view('admin.practitioners.applications', ['status' => $status,
            'rows' => DB::table('practitioner_applications')->where('status', $status)->orderByDesc('id')->paginate(30)->withQueryString()]);
    }

    public function decide(Request $request, string $ref, Applications $apps): RedirectResponse
    {
        $this->authorize('practitioners.manage');
        $a = DB::table('practitioner_applications')->where('ref', $ref)->first();
        abort_unless($a, 404);
        $d = $request->validate(['decision' => ['required', Rule::in(['accept', 'reject'])], 'note' => ['required_if:decision,reject', 'nullable', 'string', 'max:500']],
            ['note.required_if' => 'Write a short reason for the applicant.']);
        if ($d['decision'] === 'accept') {
            [, $link] = $apps->accept($a, $request->user());

            return back()->with('status', 'Accepted. The invitation was emailed to '.$a->email.'.')->with('invite_link', $link);
        }
        $apps->reject($a, $d['note'], $request->user());

        return back()->with('status', 'Application rejected; the applicant has been told.');
    }
}
