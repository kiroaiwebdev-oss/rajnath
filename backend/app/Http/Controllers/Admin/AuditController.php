<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('audit.view');
        $q = DB::table('audit_logs')->orderByDesc('id');
        if ($a = $request->string('action')->trim()->value()) {
            $q->where('action', 'like', str_replace(['%', '_'], ['\%', '\_'], $a).'%');
        }

        return view('admin.audit.index', ['logs' => $q->paginate(50)->withQueryString(), 'action' => $a]);
    }

    public function verify(AuditLogger $audit): View
    {
        $this->authorize('audit.view');

        return view('admin.audit.verify', ['result' => $audit->verify()]);
    }
}
