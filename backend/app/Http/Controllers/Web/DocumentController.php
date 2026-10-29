<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Pdf\Documents;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/** Customer documents: tax invoices / credit notes and completed reports, as HTML or PDF. */
class DocumentController extends Controller
{
    public function __construct(private Documents $docs) {}

    /** Invoice owned by the signed-in customer. */
    public static function ownInvoice(Request $request, string $ref): object
    {
        $inv = DB::table('invoices as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('i.ref', $ref)->where('o.user_id', $request->user()->id)->select('i.*')->first();
        abort_unless($inv, 404);

        return $inv;
    }

    public function invoice(Request $request, string $ref): Response
    {
        return response($this->docs->invoiceHtml(self::ownInvoice($request, $ref)))->header('Cache-Control', 'private, no-store');
    }

    public function invoicePdf(Request $request, string $ref): Response
    {
        $inv = self::ownInvoice($request, $ref);

        return $this->docs->download($this->docs->invoiceHtml($inv), str_replace('/', '-', $inv->number).'.pdf');
    }

    public function reportPdf(Request $request, string $ref): Response
    {
        $j = DB::table('report_jobs')->where('ref', $ref)->where('user_id', $request->user()->id)->where('status', 'completed')->first();
        abort_unless($j, 404);

        return $this->docs->download($this->docs->reportHtml($j), 'report-'.substr($j->ref, -8).'.pdf');
    }

    public function palmPdf(Request $request, string $ref): Response
    {
        $r = DB::table('palm_readings')->where('ref', $ref)->where('user_id', $request->user()->id)->where('status', 'completed')->first();
        abort_unless($r, 404);

        return $this->docs->download($this->docs->palmHtml($r), 'palmistry-'.substr($r->ref, -8).'.pdf');
    }
}
