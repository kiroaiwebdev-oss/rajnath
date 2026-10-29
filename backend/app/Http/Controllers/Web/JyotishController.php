<?php

namespace App\Http\Controllers\Web;

use App\Domain\Astro\Festivals;
use App\Domain\Astro\Gochar;
use App\Domain\Astro\Muhurat;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/** Public, computed pages: planetary transits (gochar), the nine planets, muhurat finder, festival calendars. */
class JyotishController extends Controller
{
    private const MIN_YEAR = 1900;

    private const MAX_YEAR = 2100;

    public function gochar(Gochar $gochar): View
    {
        try {
            $now = Cache::remember('gochar:now:'.now()->format('YmdH'), 3600, fn () => $gochar->now());
            $next = Cache::remember('gochar:next:'.now()->format('Ymd'), 86400, function () use ($gochar) {
                $from = CarbonImmutable::now('Asia/Kolkata');
                $out = [];
                foreach (Gochar::PLANETS as $p) {
                    $span = match ($p) {
                        'moon' => 4, 'sun', 'mercury', 'venus' => 70, 'mars' => 250, default => 1150
                    };
                    $out[$p] = collect($gochar->events($p, $from->toDateTimeImmutable(), $from->addDays($span)->toDateTimeImmutable(), 'Asia/Kolkata'))->firstWhere('type', 'ingress');
                }

                return $out;
            });
        } catch (RuntimeException $e) {
            report($e);
            [$now, $next] = [null, []];
        }

        return view('public.gochar.index', ['now' => $now, 'next' => $next]);
    }

    public function planet(Request $request, string $planet, Gochar $gochar): View
    {
        $d = $request->validate(['year' => ['nullable', 'integer', 'between:'.self::MIN_YEAR.','.self::MAX_YEAR], 'month' => ['nullable', 'date_format:Y-m'], 'lang' => ['nullable', 'in:hi,en']]);
        $locale = ($d['lang'] ?? 'hi') === 'en' ? 'en' : 'hi';
        $tz = 'Asia/Kolkata';
        // The Moon changes sign every ~2¼ days, so its page is by month; the others by year.
        if ($planet === 'moon') {
            $from = CarbonImmutable::parse(($d['month'] ?? now($tz)->format('Y-m')).'-01', $tz);
            abort_if($from->year < self::MIN_YEAR || $from->year > self::MAX_YEAR, 404);
            $to = $from->addMonth();
        } else {
            $from = CarbonImmutable::create((int) ($d['year'] ?? now($tz)->year), 1, 1, 0, 0, 0, $tz);
            $to = $from->addYear();
        }
        try {
            $events = Cache::remember("gochar:$planet:".$from->format('Y-m'), now()->addDays(30), fn () => $gochar->events($planet, $from->toDateTimeImmutable(), $to->toDateTimeImmutable(), $tz));
            $now = Cache::remember('gochar:now:'.now()->format('YmdH'), 3600, fn () => $gochar->now());
        } catch (RuntimeException $e) {
            report($e);
            [$events, $now] = [null, null];
        }
        $today = now($tz)->toDateString();
        $notes = DB::table('horoscopes')->where('status', 'published')->where('kind', 'gochar')->where('topic', $planet)->where('locale', $locale)
            ->where('starts_on', '<=', $today)->where('ends_on', '>=', $today)->orderBy('sign')->get()->keyBy('sign');

        return view('public.gochar.planet', ['planet' => $planet, 'events' => $events, 'now' => $now[$planet] ?? null, 'from' => $from, 'notes' => $notes, 'locale' => $locale]);
    }

    public function navagraha(Gochar $gochar): View
    {
        try {
            $now = Cache::remember('gochar:now:'.now()->format('YmdH'), 3600, fn () => $gochar->now());
        } catch (RuntimeException $e) {
            report($e);
            $now = null;
        }

        return view('public.gochar.navagraha', ['now' => $now]);
    }

    public function muhuratIndex(): View
    {
        return view('public.muhurat.index');
    }

    public function muhurat(Request $request, string $event, Muhurat $muhurat): View
    {
        $d = $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'place_id' => ['nullable', 'integer', 'exists:places,id']]);
        $place = self::place($d['place_id'] ?? null);
        $month = CarbonImmutable::parse(($d['month'] ?? now($place->tz_name)->format('Y-m')).'-01');
        abort_if($month->year < self::MIN_YEAR || $month->year > self::MAX_YEAR, 404);
        try {
            $facts = Cache::remember('muhurat:v1:'.$month->format('Y-m').':'.($place->id ?? 'patna'), now()->addDays(30), fn () => $muhurat->monthFacts($month->year, $month->month, (float) $place->lat, (float) $place->lng, $place->tz_name));
            $dates = Muhurat::candidates($event, $facts);
        } catch (RuntimeException $e) {
            report($e);
            $dates = null;
        }

        return view('public.muhurat.show', ['event' => $event, 'rule' => Muhurat::EVENTS[$event], 'dates' => $dates, 'month' => $month, 'place' => $place, 'placeLabel' => self::label($place)]);
    }

    public function festivals(Request $request, Festivals $festivals): View
    {
        $d = $request->validate(['year' => ['nullable', 'integer', 'between:'.self::MIN_YEAR.','.self::MAX_YEAR], 'religion' => ['nullable', 'in:'.implode(',', array_keys(Festivals::RELIGIONS))], 'place_id' => ['nullable', 'integer', 'exists:places,id']]);
        $place = self::place($d['place_id'] ?? null);
        $year = (int) ($d['year'] ?? now($place->tz_name)->year);
        try {
            $list = collect(ContentController::festivalYear($festivals, $year, $place));
        } catch (RuntimeException $e) {
            report($e);
            $list = null;
        }
        if ($list && ! empty($d['religion'])) {
            $list = $list->where('religion', $d['religion'])->values();
        }

        return view('public.festivals', ['list' => $list, 'year' => $year, 'religion' => $d['religion'] ?? null, 'place' => $place, 'placeLabel' => self::label($place)]);
    }

    /** A place from the places table, or Patna when none is chosen. */
    public static function place(?int $id): object
    {
        return DB::table('places')->where('id', $id ?? 1260086)->first() ?? (object) ['id' => null, 'name' => 'Patna', 'admin1' => 'Bihar', 'country' => 'India', 'lat' => 25.59408, 'lng' => 85.13563, 'tz_name' => 'Asia/Kolkata'];
    }

    private static function label(object $p): string
    {
        return implode(', ', array_filter([$p->name, $p->admin1, $p->country]));
    }
}
