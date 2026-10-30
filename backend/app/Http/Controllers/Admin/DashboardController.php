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
            // [label, count, route, permission]: work waiting for someone, shown only to people who can act on it
            'attention' => array_values(array_filter([
                ['New enquiries', DB::table('enquiries')->where('status', 'new')->count(), 'admin.enquiries.index', 'bookings.view'],
                ['Support requests open', DB::table('tickets')->whereIn('status', ['open', 'waiting_staff'])->count(), 'admin.ops.tickets', 'support.view'],
                ['Reviews to check', DB::table('reviews')->where('status', 'pending')->count(), 'admin.reviews.index', 'content.review'],
                ['Privacy requests', DB::table('privacy_requests')->whereIn('case_status', ['open', 'in_progress'])->count(), 'admin.privacy.index', 'privacy.handle'],
                ['Astrologer applications', DB::table('practitioner_applications')->where('status', 'submitted')->count(), 'admin.applications.index', 'practitioners.view'],
                ['Legal page drafts', DB::table('legal_documents')->where('status', 'draft')->count(), 'admin.legal.index', 'legal.manage'],
            ], fn ($a) => auth()->user()->can($a[3]))),
        ]);
    }
}
