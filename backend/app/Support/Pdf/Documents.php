<?php

namespace App\Support\Pdf;

use App\Domain\Palmistry\PalmReadings;
use App\Domain\Reports\ReportJobs;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Builds the HTML for invoices and reports, and serves it as HTML or as a PDF download. */
class Documents
{
    public function __construct(private Pdf $pdf) {}

    public function invoiceHtml(object $inv): string
    {
        return view('pdf.invoice', ['inv' => $inv, 'sup' => json_decode($inv->supplier, true), 'buyer' => json_decode($inv->buyer, true), 'lines' => json_decode($inv->lines, true),
            'order' => DB::table('orders')->find($inv->order_id), 'original' => $inv->invoice_id ? DB::table('invoices')->find($inv->invoice_id) : null,
            'title' => ($inv->type === 'invoice' ? 'Tax invoice ' : 'Credit note ').$inv->number, 'brand' => json_decode($inv->supplier, true)['legal_name'] ?? config('brand')['brand.name'], 'lang' => 'en'])->render();
    }

    /** Completed expert report (report_jobs row). */
    public function reportHtml(object $job): string
    {
        $o = DB::table('offerings')->where('slug', $job->offering_slug)->first();
        $title = DB::table('offering_translations')->where('offering_id', $o?->id)->where('locale', $job->language === 'hi' ? 'hi' : 'en')->value('title')
            ?? DB::table('offering_translations')->where('offering_id', $o?->id)->where('locale', 'en')->value('title') ?? 'Report';

        return view('pdf.report', ['title' => $title, 'subjects' => $job->subjects ? json_decode($job->subjects, true) : [], 'chart' => $job->chart ? json_decode($job->chart, true) : null,
            'defs' => ReportJobs::sections($job->offering_slug), 'sections' => $job->sections ? json_decode($job->sections, true) : [], 'kind' => 'report',
            'completedAt' => $job->completed_at ? Carbon::parse($job->completed_at)->tz('Asia/Kolkata')->format('d M Y') : '',
            'brand' => config('brand')['brand.name'], 'lang' => $job->language === 'hi' ? 'hi' : 'en'])->render();
    }

    /** Completed palmistry report (palm_readings row). */
    public function palmHtml(object $r): string
    {
        return view('pdf.report', ['title' => 'Palmistry report', 'subjects' => [], 'chart' => null, 'defs' => PalmReadings::SECTIONS,
            'sections' => $r->report ? json_decode($r->report, true) : [], 'kind' => 'palm',
            'completedAt' => $r->completed_at ? Carbon::parse($r->completed_at)->tz('Asia/Kolkata')->format('d M Y') : '',
            'brand' => config('brand')['brand.name'], 'lang' => 'en'])->render();
    }

    public function download(string $html, string $filename): Response
    {
        try {
            $bytes = $this->pdf->render($html);
        } catch (RuntimeException $e) {
            report($e);
            abort(503, 'PDF download is not available right now. Open the page and use Print › Save as PDF.');
        }

        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/', '-', $filename).'"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
