<?php

namespace App\Http\Controllers\Web;

use App\Domain\Astro\PanchangService;
use App\Domain\Astro\Tools;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class ToolController extends Controller
{
    public function index(): View
    {
        return view('public.tools.index', ['tools' => Tools::TOOLS]);
    }

    public function show(string $slug): View
    {
        abort_unless(isset(Tools::TOOLS[$slug]), 404);

        return view('public.tools.show', ['slug' => $slug, 'tool' => Tools::TOOLS[$slug], 'result' => null]);
    }

    /** POST so birth details never appear in URLs, logs or browser history. */
    public function compute(Request $request, string $slug, Tools $tools): View
    {
        abort_unless(isset(Tools::TOOLS[$slug]), 404);
        $in = $request->validate(Tools::rules(Tools::TOOLS[$slug][1]));
        try {
            $result = $tools->compute($slug, $in);
        } catch (RuntimeException $e) {
            report($e);

            return view('public.tools.show', ['slug' => $slug, 'tool' => Tools::TOOLS[$slug], 'result' => null, 'engineError' => true]);
        }

        return view('public.tools.show', ['slug' => $slug, 'tool' => Tools::TOOLS[$slug], 'result' => $result]);
    }

    public function panchang(Request $request, PanchangService $panchang): View
    {
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1800-01-01', 'before_or_equal:2399-12-31'], 'place_id' => ['nullable', 'integer', 'exists:places,id']]);
        $place = DB::table('places')->where('id', $data['place_id'] ?? 1260086)->first() ?? (object) ['id' => null, 'name' => 'Patna', 'admin1' => 'Bihar', 'country' => 'India', 'lat' => 25.59408, 'lng' => 85.13563, 'tz_name' => 'Asia/Kolkata'];
        $date = $data['date'] ?? now($place->tz_name)->format('Y-m-d');
        try {
            $p = $panchang->day($date, (float) $place->lat, (float) $place->lng, $place->tz_name);
        } catch (RuntimeException $e) {
            report($e);
            $p = null;
        }

        return view('public.panchang', ['p' => $p, 'date' => $date, 'place' => $place, 'placeLabel' => implode(', ', array_filter([$place->name, $place->admin1, $place->country]))]);
    }
}
