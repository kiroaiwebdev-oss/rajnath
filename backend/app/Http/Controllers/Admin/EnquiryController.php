<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('bookings.view');
        $status = (string) $request->query('status', 'new');
        $list = Enquiry::with('offering.translations')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()->paginate(30)->withQueryString();

        return view('admin.enquiries.index', ['list' => $list, 'status' => $status, 'counts' => Enquiry::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status')]);
    }

    public function show(Enquiry $enquiry, AuditLogger $audit): View
    {
        $this->authorize('bookings.view');
        $audit->record('enquiry.viewed', $enquiry); // personal data access is audited

        return view('admin.enquiries.show', ['e' => $enquiry->load('offering.translations')]);
    }

    public function update(Request $request, Enquiry $enquiry, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('bookings.manage');
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Enquiry::STATUSES))], 'staff_note' => ['nullable', 'string', 'max:2000']]);
        $before = $enquiry->status;
        $enquiry->forceFill($data + ['handled_by' => $request->user()->id])->save();
        $audit->record('enquiry.updated', $enquiry, ['from' => $before, 'to' => $data['status']]);

        return redirect()->route('admin.enquiries.index')->with('status', 'Enquiry updated.');
    }

    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $this->authorize('bookings.manage');
        $audit->record('enquiry.exported', null, ['status' => $request->query('status', 'all')]);

        return response()->streamDownload(function () use ($request) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Received (IST)', 'Status', 'Service', 'Name', 'Phone', 'Email', 'Mode', 'Language', 'Preferred date', 'Message']);
            Enquiry::with('offering.translations')
                ->when($request->query('status', 'all') !== 'all', fn ($q) => $q->where('status', $request->query('status')))
                ->orderBy('id')->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $e) {
                        fputcsv($out, array_map([self::class, 'csvSafe'], [$e->ref, $e->created_at->tz('Asia/Kolkata')->format('Y-m-d H:i'), $e->status, $e->offering?->t()?->title,
                            $e->name, $e->phone_e164, $e->email, $e->mode, $e->language, $e->preferred_date?->format('Y-m-d'), $e->message]));
                    }
                });
            fclose($out);
        }, 'enquiries-'.now('Asia/Kolkata')->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Prevents spreadsheet formula injection. */
    public static function csvSafe(?string $v): string
    {
        $v = (string) $v;

        return preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
    }
}
