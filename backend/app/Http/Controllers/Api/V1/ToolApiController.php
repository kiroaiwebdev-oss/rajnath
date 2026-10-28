<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Astro\PanchangService;
use App\Domain\Astro\Tools;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ToolApiController extends Controller
{
    public function compute(Request $request, string $slug, Tools $tools): JsonResponse
    {
        if (! isset(Tools::TOOLS[$slug])) {
            return AuthController::error('not_found', 'Unknown tool.', 404);
        }
        $in = $request->validate(Tools::rules(Tools::TOOLS[$slug][1]));
        try {
            return response()->json(['data' => $tools->compute($slug, $in)]);
        } catch (RuntimeException $e) {
            report($e);

            return AuthController::error('engine_unavailable', 'The calculation service is unavailable. Please try again shortly.', 503);
        }
    }

    public function panchang(Request $request, PanchangService $panchang): JsonResponse
    {
        $d = $request->validate(['date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1800-01-01', 'before_or_equal:2399-12-31'], 'place_id' => ['required', 'integer', 'exists:places,id']]);
        $p = DB::table('places')->where('id', $d['place_id'])->first();
        try {
            return response()->json(['data' => $panchang->day($d['date'], (float) $p->lat, (float) $p->lng, $p->tz_name)]);
        } catch (RuntimeException $e) {
            report($e);

            return AuthController::error('engine_unavailable', 'The calculation service is unavailable. Please try again shortly.', 503);
        }
    }
}
