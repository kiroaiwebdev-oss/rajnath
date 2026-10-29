<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Practitioners\Applications;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** App: apply to join as an astrologer (AN-12). */
class ApplicationApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => DB::table('practitioner_applications')->where('user_id', $request->user()->id)->orderByDesc('id')->get()->map(fn ($a) => Applications::toApi($a))]);
    }

    public function store(Request $request, Applications $apps): JsonResponse
    {
        $a = $apps->submit($request->user(), $request->validate(Applications::rules(), Applications::messages()));

        return response()->json(['data' => Applications::toApi($a)], 201);
    }

    public function withdraw(Request $request, string $ref, Applications $apps): JsonResponse
    {
        $a = DB::table('practitioner_applications')->where('ref', $ref)->where('user_id', $request->user()->id)->first();
        abort_unless($a, 404);
        $apps->withdraw($a, $request->user());

        return response()->json(['data' => Applications::toApi(DB::table('practitioner_applications')->find($a->id))]);
    }
}
