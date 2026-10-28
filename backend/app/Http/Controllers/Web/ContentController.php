<?php

namespace App\Http\Controllers\Web;

use App\Domain\Astro\VratCalendar;
use App\Domain\Astro\Vedic;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class ContentController extends Controller
{
    public const SIGNS_HI = ['मेष', 'वृषभ', 'मिथुन', 'कर्क', 'सिंह', 'कन्या', 'तुला', 'वृश्चिक', 'धनु', 'मकर', 'कुंभ', 'मीन'];

    /** Only published horoscopes whose period contains today are shown (no stale content). */
    public function rashifal(Request $request): View
    {
        $period = in_array($request->query('period'), ['daily', 'weekly', 'monthly', 'yearly'], true) ? $request->query('period') : 'daily';
        $locale = $request->query('lang') === 'en' ? 'en' : 'hi';
        $today = now('Asia/Kolkata')->toDateString();
        $rows = DB::table('horoscopes')->where('status', 'published')->where('period', $period)->where('locale', $locale)
            ->where('starts_on', '<=', $today)->where('ends_on', '>=', $today)->orderBy('sign')->get()->keyBy('sign');

        return view('public.rashifal', ['rows' => $rows, 'period' => $period, 'locale' => $locale]);
    }

    public function calendar(Request $request, VratCalendar $cal): View
    {
        $d = $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'place_id' => ['nullable', 'integer', 'exists:places,id']]);
        $month = CarbonImmutable::parse(($d['month'] ?? now('Asia/Kolkata')->format('Y-m')).'-01');
        if ($month->year < 1800 || $month->year > 2399) {
            abort(404);
        }
        $place = DB::table('places')->where('id', $d['place_id'] ?? 1260086)->first() ?? (object) ['id' => null, 'name' => 'Patna', 'admin1' => 'Bihar', 'country' => 'India', 'lat' => 25.59408, 'lng' => 85.13563, 'tz_name' => 'Asia/Kolkata'];
        try {
            $days = Cache::remember('vrat:'.$month->format('Y-m').':'.($place->id ?? 'patna'), now()->addDays(30), fn () => $cal->month($month->year, $month->month, (float) $place->lat, (float) $place->lng, $place->tz_name));
        } catch (RuntimeException $e) {
            report($e);
            $days = null;
        }

        return view('public.calendar', ['days' => $days, 'month' => $month, 'place' => $place, 'placeLabel' => implode(', ', array_filter([$place->name, $place->admin1, $place->country]))]);
    }
}
