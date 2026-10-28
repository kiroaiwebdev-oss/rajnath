<?php

namespace App\Http\Controllers\Web;

use App\Domain\Palmistry\PalmReadings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PalmController extends Controller
{
    public function __construct(private PalmReadings $palm) {}

    private function mine(Request $request, string $ref): object
    {
        $r = DB::table('palm_readings')->where('ref', $ref)->where('user_id', $request->user()->id)->first();
        abort_unless($r, 404);

        return $r;
    }

    public function index(Request $request): View
    {
        return view('account.palm.index', ['readings' => DB::table('palm_readings')->where('user_id', $request->user()->id)->orderByDesc('id')->get()]);
    }

    public function show(Request $request, string $ref): View
    {
        $r = $this->mine($request, $ref);

        return view('account.palm.show', ['r' => $r, 'report' => $r->report ? json_decode($r->report, true) : null]);
    }

    public function upload(Request $request, string $ref): RedirectResponse
    {
        $r = $this->mine($request, $ref);
        $d = $request->validate([
            'left' => ['required', 'file', 'max:8192'],
            'right' => ['required', 'file', 'max:8192'],
            'dominant_hand' => ['required', Rule::in(['left', 'right'])],
            'age' => ['nullable', 'integer', 'min:1', 'max:120'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'language' => ['nullable', Rule::in(['en', 'hi'])],
            'question' => ['nullable', 'string', 'max:1000'],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'Please agree to the use of your palm photos for this report.']);
        $this->palm->submitPhotos($r, $request->user(), $request->file('left'), $request->file('right'), $d);

        return redirect()->route('account.palm.show', $ref)->with('status', 'Photos received. Acharya Ji\'s team will prepare your report and email you when it is ready.');
    }

    /** Streams a private palm photo to its owner or to authorised fulfilment staff only. */
    public function photo(Request $request, string $ref, string $side): StreamedResponse
    {
        abort_unless(in_array($side, ['left', 'right'], true), 404);
        $r = DB::table('palm_readings')->where('ref', $ref)->first();
        $u = $request->user();
        abort_unless($r && $u && ($r->user_id === $u->id || ($u->isStaff() && $request->session()->has('staff_mfa_at') && $u->can('reports.fulfil'))), 404);
        $path = $side === 'left' ? $r->left_photo : $r->right_photo;
        abort_unless($path && Storage::disk(PalmReadings::DISK)->exists($path), 404);
        if ($u->isStaff()) {
            app(\App\Support\Audit\AuditLogger::class)->record('palm.photo_viewed', null, ['reading' => $r->ref, 'side' => $side], $u);
        }

        return Storage::disk(PalmReadings::DISK)->response($path, null, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Disposition' => 'inline']);
    }
}
