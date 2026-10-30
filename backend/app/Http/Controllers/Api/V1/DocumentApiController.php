<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FileLinkController;
use App\Http\Controllers\Web\DocumentController;
use App\Support\Pdf\Documents;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/** App: PDF downloads of invoices, credit notes and completed reports (bearer token). */
class DocumentApiController extends Controller
{
    public function __construct(private Documents $docs, private DocumentController $web) {}

    public static function invoicesFor(object $order): array
    {
        return DB::table('invoices')->where('order_id', $order->id)->orderBy('id')->get()
            ->map(fn ($i) => ['ref' => $i->ref, 'type' => $i->type, 'number' => $i->number, 'total_minor' => (int) $i->total_minor,
                'tax_minor' => (int) $i->cgst_minor + (int) $i->sgst_minor + (int) $i->igst_minor, 'pdf_path' => '/invoices/'.$i->ref.'/pdf',
                'pdf_url' => FileLinkController::url('invoice', $i->ref, (int) $order->user_id)])->all();
    }

    public function invoicePdf(Request $request, string $ref): Response
    {
        return $this->web->invoicePdf($request, $ref);
    }

    public function reportPdf(Request $request, string $ref): Response
    {
        return $this->web->reportPdf($request, $ref);
    }

    public function palmPdf(Request $request, string $ref): Response
    {
        return $this->web->palmPdf($request, $ref);
    }
}
