<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'Staff accounts' => User::whereHas('roles')->count(),
                'Customers' => User::where('kind', User::KIND_CUSTOMER)->count(),
                'Pending invitations' => DB::table('staff_invitations')->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count(),
                'Audit events (24 h)' => DB::table('audit_logs')->where('created_at', '>', now()->subDay())->count(),
            ],
            'recent' => DB::table('audit_logs')->orderByDesc('id')->limit(8)->get(),
        ]);
    }
}
