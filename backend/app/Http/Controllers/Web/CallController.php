<?php

namespace App\Http\Controllers\Web;

use App\Domain\Booking\Slots;
use App\Domain\Meetings\LiveKit;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** In-app consultation call (website) and the token endpoint used by the website and the app. */
class CallController extends Controller
{
    public function __construct(private LiveKit $lk) {}

    private function booking(Request $r, string $ref): object
    {
        $b = DB::table('bookings')->where('ref', $ref)->first();
        abort_unless($b && LiveKit::participant($b, $r->user()), 404);
        if ($r->user()->isStaff() && ! $r->is('api/*')) {
            abort_unless($r->session()->has('staff_mfa_at'), 404);
        }

        return $b;
    }

    public function page(Request $r, string $ref): View
    {
        $b = $this->booking($r, $ref);
        $title = DB::table('offering_variants as v')->join('offering_translations as t', fn ($j) => $j->on('t.offering_id', '=', 'v.offering_id')->where('t.locale', 'en'))->where('v.id', $b->variant_id)->value('t.title');

        return view('public.call', ['b' => $b, 'title' => $title, 'when' => Slots::when($b), 'audioOnly' => $b->mode === 'audio', 'isStaff' => $r->user()->isStaff()]);
    }

    public function token(Request $r, string $ref): JsonResponse
    {
        $b = $this->booking($r, $ref);
        try {
            $t = $this->lk->tokenFor($b, $r->user());
        } catch (ValidationException $e) {
            return response()->json(['error' => ['code' => 'call_not_available', 'message' => collect($e->errors())->flatten()->first()]], 422);
        }
        app(\App\Support\Audit\AuditLogger::class)->record('call.joined', null, ['booking' => $b->ref, 'identity' => $t['identity']], $r->user());

        return response()->json($t);
    }
}
