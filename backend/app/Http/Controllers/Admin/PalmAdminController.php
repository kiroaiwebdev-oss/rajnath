<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Palmistry\PalmReadings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PalmAdminController extends Controller
{
    public function __construct(private PalmReadings $palm) {}

    public function index(Request $request): View
    {
        $this->authorize('reports.fulfil');
        $status = (string) $request->query('status', 'open');

        return view('admin.palm.index', ['status' => $status, 'rows' => DB::table('palm_readings as p')->join('users as u', 'u.id', '=', 'p.user_id')
            ->when($status === 'open', fn ($q) => $q->whereIn('p.status', ['submitted', 'in_review']))
            ->when(! in_array($status, ['open', 'all'], true), fn ($q) => $q->where('p.status', $status))
            ->select('p.*', 'u.name as customer')->orderBy('p.submitted_at')->paginate(30)->withQueryString()]);
    }

    public function show(string $ref): View
    {
        $this->authorize('reports.fulfil');
        $r = DB::table('palm_readings')->where('ref', $ref)->first();
        abort_unless($r, 404);

        return view('admin.palm.show', ['r' => $r, 'report' => $r->report ? json_decode($r->report, true) : [], 'customer' => DB::table('users')->find($r->user_id)]);
    }

    public function claim(Request $request, string $ref): RedirectResponse
    {
        $this->authorize('reports.fulfil');
        $this->palm->claim($this->find($ref), $request->user());

        return back()->with('status', 'Assigned to you.');
    }

    public function report(Request $request, string $ref): RedirectResponse
    {
        $this->authorize('reports.fulfil');
        $rules = [];
        foreach (PalmReadings::SECTIONS as $k => $_) {
            $rules["sections.$k"] = ['nullable', 'string', 'max:5000'];
        }
        $d = $request->validate($rules + ['action' => ['required', 'in:draft,complete']]);
        $this->palm->saveReport($this->find($ref), $d['sections'] ?? [], $d['action'] === 'complete', $request->user());

        return back()->with('status', $d['action'] === 'complete' ? 'Report published to the customer.' : 'Draft saved.');
    }

    public function needPhotos(Request $request, string $ref): RedirectResponse
    {
        $this->authorize('reports.fulfil');
        $note = $request->validate(['note' => ['required', 'string', 'max:500']])['note'];
        $this->palm->requestNewPhotos($this->find($ref), $note, $request->user());

        return back()->with('status', 'Customer asked for new photos.');
    }

    private function find(string $ref): object
    {
        $r = DB::table('palm_readings')->where('ref', $ref)->first();
        abort_unless($r, 404);

        return $r;
    }
}
