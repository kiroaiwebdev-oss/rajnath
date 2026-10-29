<?php

namespace App\Http\Controllers\Web;

use App\Domain\Astro\Festivals;
use App\Domain\Astro\Gochar;
use App\Domain\Astro\VratCalendar;
use App\Domain\Astro\Vedic;
use App\Domain\Content\HoroscopeTypes;
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

    public const SIGN_SYMBOLS = ['Ram', 'Bull', 'Twins', 'Crab', 'Lion', 'Maiden', 'Balance (scales)', 'Scorpion', 'Bow (archer)', 'Makara (crocodile)', 'Water-pot bearer', 'Pair of fish'];

    /** Default period shown for each kind when none is chosen. */
    private const DEFAULT_PERIOD = ['rashi' => 'daily', 'nakshatra' => 'daily', 'chinese' => 'yearly', 'numerology' => 'monthly', 'tarot' => 'yearly'];

    /**
     * Published horoscopes. Daily/weekly/monthly show only the entry whose period contains today
     * (no stale content); yearly and half-yearly can also be read by year (?year=2027).
     */
    public function rashifal(Request $request, string $kind = 'rashi'): View
    {
        abort_unless(in_array($kind, HoroscopeTypes::PUBLIC_KINDS, true), 404);
        $period = array_key_exists((string) $request->query('period'), HoroscopeTypes::PERIODS) ? $request->query('period') : self::DEFAULT_PERIOD[$kind];
        $topic = $kind === 'rashi' && array_key_exists((string) $request->query('topic'), HoroscopeTypes::TOPICS) ? $request->query('topic') : 'general';
        $locale = $request->query('lang') === 'en' ? 'en' : 'hi';
        $today = now('Asia/Kolkata');
        $year = in_array($period, ['yearly', 'halfyearly'], true) && ctype_digit((string) $request->query('year')) && (int) $request->query('year') >= 2000 && (int) $request->query('year') <= 2100
            ? (int) $request->query('year') : null;
        $q = DB::table('horoscopes')->where('status', 'published')->where('kind', $kind)->where('topic', $topic)->where('period', $period)->where('locale', $locale);
        if ($year !== null) {
            $q->whereYear('starts_on', $year);
        } else {
            $q->where('starts_on', '<=', $today->toDateString())->where('ends_on', '>=', $today->toDateString());
        }
        $rows = $q->orderBy('sign')->orderBy('starts_on')->get()->groupBy('sign');

        return view('public.rashifal', ['rows' => $rows, 'kind' => $kind, 'period' => $period, 'topic' => $topic, 'locale' => $locale, 'year' => $year,
            'years' => [$today->year, $today->year + 1], 'names' => HoroscopeTypes::subjects($kind, $locale)]);
    }

    /** Zodiac sign guide: classical facts, current transits counted from this Moon sign, published Rashifal. */
    public function rashi(Request $request, string $sign, Gochar $gochar): View
    {
        $i = array_search($sign, self::signSlugs(), true);
        abort_if($i === false, 404);
        $locale = $request->query('lang') === 'en' ? 'en' : 'hi';
        $today = now('Asia/Kolkata')->toDateString();
        $rows = DB::table('horoscopes')->where('status', 'published')->where('kind', 'rashi')->where('sign', $i)->where('locale', $locale)
            ->where('starts_on', '<=', $today)->where('ends_on', '>=', $today)->orderBy('topic')->orderBy('period')->get();
        try {
            $now = Cache::remember('gochar:now:'.now()->format('YmdH'), 3600, fn () => $gochar->now());
            $year = (int) now('Asia/Kolkata')->format('Y');
            $sun = Cache::remember("gochar:sun-in-sign:$year", now()->addDays(30), fn () => $gochar->events('sun', CarbonImmutable::create($year, 1, 1, 0, 0, 0, 'Asia/Kolkata')->toDateTimeImmutable(), CarbonImmutable::create($year + 1, 1, 1, 0, 0, 0, 'Asia/Kolkata')->toDateTimeImmutable(), 'Asia/Kolkata'));
            $sunIn = collect($sun)->where('type', 'ingress')->firstWhere('sign', $i);
            $sunOut = collect($sun)->where('type', 'ingress')->firstWhere('sign', ($i + 1) % 12);
        } catch (RuntimeException $e) {
            report($e);
            [$now, $sunIn, $sunOut] = [null, null, null];
        }

        return view('public.rashi', ['i' => $i, 'locale' => $locale, 'rows' => $rows, 'now' => $now, 'sunIn' => $sunIn, 'sunOut' => $sunOut, 'nakshatras' => self::nakshatrasIn($i)]);
    }

    /** URL slugs for the 12 signs: mesha, vrishabha, … meena. */
    public static function signSlugs(): array
    {
        return array_map(fn ($s) => strtolower(strtok($s, ' ')), Vedic::SIGNS);
    }

    /** Nakshatras (with padas) that fall in a sign: each sign holds 9 padas. */
    public static function nakshatrasIn(int $sign): array
    {
        $out = [];
        for ($p = 9 * $sign; $p < 9 * $sign + 9; $p++) {
            $out[Vedic::NAKSHATRAS[intdiv($p, 4)]][] = $p % 4 + 1;
        }

        return array_map(fn ($padas) => min($padas) === max($padas) ? (string) min($padas) : min($padas).'–'.max($padas), $out);
    }

    public function calendar(Request $request, VratCalendar $cal, Festivals $festivals): View
    {
        $d = $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'place_id' => ['nullable', 'integer', 'exists:places,id']]);
        $month = CarbonImmutable::parse(($d['month'] ?? now('Asia/Kolkata')->format('Y-m')).'-01');
        if ($month->year < 1800 || $month->year > 2399) {
            abort(404);
        }
        $place = DB::table('places')->where('id', $d['place_id'] ?? 1260086)->first() ?? (object) ['id' => null, 'name' => 'Patna', 'admin1' => 'Bihar', 'country' => 'India', 'lat' => 25.59408, 'lng' => 85.13563, 'tz_name' => 'Asia/Kolkata'];
        try {
            $days = Cache::remember('vrat:'.$month->format('Y-m').':'.($place->id ?? 'patna'), now()->addDays(30), fn () => $cal->month($month->year, $month->month, (float) $place->lat, (float) $place->lng, $place->tz_name));
            if ($month->year >= 1900 && $month->year <= 2100) {
                foreach (self::festivalYear($festivals, $month->year, $place) as $f) {
                    if (isset($days[$f['date']])) {
                        $days[$f['date']]['events'][] = $f['name'].($f['religion'] !== 'hindu' ? ' ('.Festivals::RELIGIONS[$f['religion']].')' : '');
                    }
                }
            }
        } catch (RuntimeException $e) {
            report($e);
            $days = null;
        }

        return view('public.calendar', ['days' => $days, 'month' => $month, 'place' => $place, 'placeLabel' => implode(', ', array_filter([$place->name, $place->admin1, $place->country]))]);
    }

    /** Festival list for a year and place, cached for 30 days. */
    public static function festivalYear(Festivals $festivals, int $year, object $place): array
    {
        return Cache::remember("festivals:v1:$year:".($place->id ?? 'patna'), now()->addDays(30), fn () => $festivals->year($year, (float) $place->lat, (float) $place->lng, $place->tz_name));
    }
}
