<?php

namespace App\Http\Controllers\Web;

use App\Domain\Practitioners\Applications;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Account › Join as an astrologer (website); the app uses the API equivalent. */
class ApplyController extends Controller
{
    public function show(Request $request): View
    {
        return view('account.apply', ['apps' => DB::table('practitioner_applications')->where('user_id', $request->user()->id)->orderByDesc('id')->get(), 'user' => $request->user()]);
    }

    public function store(Request $request, Applications $apps): RedirectResponse
    {
        $apps->submit($request->user(), $request->validate(Applications::rules(), Applications::messages()));

        return back()->with('status', 'Thank you. Your application has been sent to our team.');
    }

    public function withdraw(Request $request, string $ref, Applications $apps): RedirectResponse
    {
        $a = DB::table('practitioner_applications')->where('ref', $ref)->where('user_id', $request->user()->id)->first();
        abort_unless($a, 404);
        $apps->withdraw($a, $request->user());

        return back()->with('status', 'Application withdrawn.');
    }
}
