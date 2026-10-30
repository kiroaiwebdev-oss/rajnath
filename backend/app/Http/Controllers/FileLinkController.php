<?php

namespace App\Http\Controllers;

use App\Domain\Puja\PujaBookings;
use App\Support\Pdf\Documents;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Short-lived signed links (15 minutes) so the app can open the customer's own PDFs and puja
 * photos in the phone's browser or image viewer without a session. The link is issued only to
 * the owner through the authenticated API and names the owner (u), which is checked again.
 */
class FileLinkController extends Controller
{
    public const KINDS = ['invoice', 'report', 'palm', 'puja-photo'];

    public static function url(string $kind, string $ref, int $userId, int $index = 0): string
    {
        return URL::temporarySignedRoute('files.show', now()->addMinutes(15), ['kind' => $kind, 'ref' => $ref, 'i' => $index, 'u' => $userId]);
    }

    public function __invoke(string $kind, string $ref, Documents $docs): Response
    {
        $u = (int) request()->query('u');
        $i = (int) request()->query('i');
        switch ($kind) {
            case 'invoice':
                $inv = DB::table('invoices as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('i.ref', $ref)->where('o.user_id', $u)->select('i.*')->first();
                abort_unless($inv, 404);

                return $docs->download($docs->invoiceHtml($inv), str_replace('/', '-', $inv->number).'.pdf');
            case 'report':
                $j = DB::table('report_jobs')->where('ref', $ref)->where('user_id', $u)->where('status', 'completed')->first();
                abort_unless($j, 404);

                return $docs->download($docs->reportHtml($j), 'report-'.substr($j->ref, -8).'.pdf');
            case 'palm':
                $p = DB::table('palm_readings')->where('ref', $ref)->where('user_id', $u)->where('status', 'completed')->first();
                abort_unless($p, 404);

                return $docs->download($docs->palmHtml($p), 'palmistry-'.substr($p->ref, -8).'.pdf');
            default:
                $b = DB::table('puja_bookings')->where('ref', $ref)->where('user_id', $u)->first();
                $path = $b ? ((json_decode($b->evidence ?? '{}', true)['photos'] ?? [])[$i] ?? null) : null;
                abort_unless($path && Storage::disk(PujaBookings::DISK)->exists($path), 404);

                return Storage::disk(PujaBookings::DISK)->response($path, null, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        }
    }
}
