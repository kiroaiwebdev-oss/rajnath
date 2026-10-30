<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Astro\Festivals;
use App\Domain\Astro\Gochar;
use App\Domain\Astro\Muhurat;
use App\Domain\Astro\Vedic;
use App\Domain\Astro\VratCalendar;
use App\Domain\Content\HoroscopeTypes;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\ContentController;
use App\Http\Controllers\Web\JyotishController;
use App\Support\Business;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** App: published horoscopes, planetary transits, muhurat dates and festival calendars. */
class ContentApiController extends Controller
{
    private function engineDown(RuntimeException $e): JsonResponse
    {
        report($e);

        return AuthController::error('engine_unavailable', 'This could not be calculated right now. Please try again shortly.', 503);
    }

    /** Lists of kinds, periods and topics with Hindi and English labels. */
    public function horoscopeTypes(): JsonResponse
    {
        $pairs = fn (array $a) => collect($a)->map(fn ($l, $k) => ['key' => $k, 'hi' => $l[0], 'en' => $l[1]])->values();

        return response()->json(['data' => ['kinds' => $pairs(array_intersect_key(HoroscopeTypes::KINDS, array_flip(HoroscopeTypes::PUBLIC_KINDS))),
            'periods' => $pairs(HoroscopeTypes::PERIODS), 'topics' => $pairs(HoroscopeTypes::TOPICS)]]);
    }

    public function horoscopes(Request $request): JsonResponse
    {
        $d = $request->validate(['kind' => ['nullable', 'in:'.implode(',', HoroscopeTypes::PUBLIC_KINDS)], 'period' => ['nullable', 'in:'.implode(',', array_keys(HoroscopeTypes::PERIODS))],
            'topic' => ['nullable', 'in:'.implode(',', array_keys(HoroscopeTypes::TOPICS))], 'lang' => ['nullable', 'in:hi,en'], 'year' => ['nullable', 'integer', 'between:2000,2100']]);
        $kind = $d['kind'] ?? 'rashi';
        $period = $d['period'] ?? 'daily';
        $topic = $kind === 'rashi' ? ($d['topic'] ?? 'general') : 'general';
        $locale = $d['lang'] ?? 'hi';
        $today = now('Asia/Kolkata')->toDateString();
        $q = DB::table('horoscopes')->where('status', 'published')->where(compact('kind', 'topic', 'period', 'locale'));
        if (! empty($d['year']) && in_array($period, ['yearly', 'halfyearly'], true)) {
            $q->whereYear('starts_on', $d['year']);
        } else {
            $q->where('starts_on', '<=', $today)->where('ends_on', '>=', $today);
        }
        $names = HoroscopeTypes::subjects($kind, $locale);

        return response()->json(['data' => $q->orderBy('sign')->orderBy('starts_on')->get()->map(fn ($r) => ['subject' => (int) $r->sign, 'name' => $names[$r->sign] ?? (string) $r->sign,
            'starts_on' => $r->starts_on, 'ends_on' => $r->ends_on, 'body' => $r->body])->values(),
            'meta' => ['kind' => $kind, 'period' => $period, 'topic' => $topic, 'lang' => $locale, 'subjects' => $names]]);
    }

    public function gochar(Gochar $gochar): JsonResponse
    {
        try {
            $now = Cache::remember('gochar:now:'.now()->format('YmdH'), 3600, fn () => $gochar->now());
        } catch (RuntimeException $e) {
            return $this->engineDown($e);
        }

        return response()->json(['data' => collect($now)->map(fn ($p, $k) => ['planet' => $k, 'name' => Vedic::PLANET_NAMES[$k], 'hi' => Gochar::HI[$k], 'sign' => $p['sign'],
            'sign_name' => Vedic::SIGNS[$p['sign']], 'degree' => $p['deg'], 'nakshatra' => $p['nakshatra'], 'retro' => $p['retro']])->values()]);
    }

    public function gocharPlanet(Request $request, string $planet, Gochar $gochar): JsonResponse
    {
        abort_unless(in_array($planet, Gochar::PLANETS, true), 404);
        $d = $request->validate(['year' => ['nullable', 'integer', 'between:1900,2100'], 'month' => ['nullable', 'date_format:Y-m'], 'lang' => ['nullable', 'in:hi,en']]);
        $tz = 'Asia/Kolkata';
        if ($planet === 'moon') {
            $from = CarbonImmutable::parse(($d['month'] ?? now($tz)->format('Y-m')).'-01', $tz);
            $to = $from->addMonth();
        } else {
            $from = CarbonImmutable::create((int) ($d['year'] ?? now($tz)->year), 1, 1, 0, 0, 0, $tz);
            $to = $from->addYear();
        }
        try {
            $events = Cache::remember("gochar:$planet:".$from->format('Y-m'), now()->addDays(30), fn () => $gochar->events($planet, $from->toDateTimeImmutable(), $to->toDateTimeImmutable(), $tz));
            $now = Cache::remember('gochar:now:'.now()->format('YmdH'), 3600, fn () => $gochar->now())[$planet];
        } catch (RuntimeException $e) {
            return $this->engineDown($e);
        }
        $today = now($tz)->toDateString();
        $notes = DB::table('horoscopes')->where('status', 'published')->where('kind', 'gochar')->where('topic', $planet)->where('locale', $d['lang'] ?? 'hi')
            ->where('starts_on', '<=', $today)->where('ends_on', '>=', $today)->pluck('body', 'sign');

        return response()->json(['data' => [
            'planet' => $planet, 'name' => Vedic::PLANET_NAMES[$planet], 'hi' => Gochar::HI[$planet],
            'now' => ['sign' => $now['sign'], 'sign_name' => Vedic::SIGNS[$now['sign']], 'degree' => $now['deg'], 'nakshatra' => $now['nakshatra'], 'retro' => $now['retro']],
            'events' => collect($events)->map(fn ($e) => ['type' => $e['type'], 'at' => $e['at']->format(DATE_ATOM), 'sign' => $e['sign'], 'sign_name' => Vedic::SIGNS[$e['sign']],
                'retro' => $e['retro'] ?? null, 'degree' => $e['deg'] ?? null])->values(),
            'by_moon_sign' => collect(Gochar::fromMoon($planet, $now['sign']))->map(fn ($h, $moon) => ['moon_sign' => $moon, 'name' => Vedic::SIGNS[$moon], 'hi' => ContentController::SIGNS_HI[$moon],
                'house' => $h['house'], 'favourable' => $h['good'], 'note' => $notes[$moon] ?? null])->values(),
            'facts' => Gochar::FACTS[$planet] + ['nakshatras' => Gochar::nakshatras($planet), 'dasha_years' => Vedic::DASHA_YEARS[$planet]],
        ]]);
    }

    public function muhuratEvents(): JsonResponse
    {
        return response()->json(['data' => collect(Muhurat::EVENTS)->map(fn ($e, $k) => ['key' => $k, 'name' => $e['name'], 'hi' => $e['hi']])->values()]);
    }

    public function muhurat(Request $request, string $event, Muhurat $muhurat): JsonResponse
    {
        abort_unless(isset(Muhurat::EVENTS[$event]), 404);
        $d = $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'place_id' => ['nullable', 'integer', 'exists:places,id']]);
        $place = JyotishController::place($d['place_id'] ?? null);
        $month = CarbonImmutable::parse(($d['month'] ?? now($place->tz_name)->format('Y-m')).'-01');
        if ($month->year < 1900 || $month->year > 2100) {
            return AuthController::error('out_of_range', 'Choose a month between 1900 and 2100.', 422);
        }
        try {
            $facts = Cache::remember('muhurat:v1:'.$month->format('Y-m').':'.($place->id ?? 'patna'), now()->addDays(30), fn () => $muhurat->monthFacts($month->year, $month->month, (float) $place->lat, (float) $place->lng, $place->tz_name));
        } catch (RuntimeException $e) {
            return $this->engineDown($e);
        }

        return response()->json(['data' => collect(Muhurat::candidates($event, $facts))->map(fn ($c) => ['date' => $c['date'], 'masa' => $c['facts']['masa'], 'masa_purnimanta' => $c['facts']['masa_purnimanta'] ?? $c['facts']['masa'],
            'windows' => array_map(fn ($w) => ['start' => $w[0], 'end' => $w[1], 'tithi' => $w[2], 'nakshatra' => $w[3]], $c['windows'])])->values(),
            'meta' => ['event' => $event, 'name' => Muhurat::EVENTS[$event]['name'], 'month' => $month->format('Y-m'), 'place' => ['id' => $place->id, 'name' => $place->name],
                'note' => 'Dates follow the Panchang rules for this ceremony. Confirm the final muhurat with Acharya Ji.']]);
    }

    public function festivals(Request $request, Festivals $festivals): JsonResponse
    {
        $d = $request->validate(['year' => ['nullable', 'integer', 'between:1900,2100'], 'religion' => ['nullable', 'in:'.implode(',', array_keys(Festivals::RELIGIONS))], 'place_id' => ['nullable', 'integer', 'exists:places,id']]);
        $place = JyotishController::place($d['place_id'] ?? null);
        try {
            $list = collect(ContentController::festivalYear($festivals, (int) ($d['year'] ?? now($place->tz_name)->year), $place));
        } catch (RuntimeException $e) {
            return $this->engineDown($e);
        }

        return response()->json(['data' => $list->when($d['religion'] ?? null, fn ($c, $r) => $c->where('religion', $r))->values(),
            'meta' => ['religions' => Festivals::RELIGIONS, 'place' => ['id' => $place->id, 'name' => $place->name]]]);
    }

    /** Monthly vrat and festival calendar (same data as /calendar). */
    public function calendar(Request $request, VratCalendar $cal, Festivals $festivals): JsonResponse
    {
        $d = $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'place_id' => ['nullable', 'integer', 'exists:places,id']]);
        $place = JyotishController::place($d['place_id'] ?? null);
        $month = CarbonImmutable::parse(($d['month'] ?? now($place->tz_name)->format('Y-m')).'-01');
        if ($month->year < 1900 || $month->year > 2100) {
            return AuthController::error('out_of_range', 'Choose a month between 1900 and 2100.', 422);
        }
        try {
            $days = Cache::remember('vrat:'.$month->format('Y-m').':'.($place->id ?? 'patna'), now()->addDays(30), fn () => $cal->month($month->year, $month->month, (float) $place->lat, (float) $place->lng, $place->tz_name));
            foreach (ContentController::festivalYear($festivals, $month->year, $place) as $f) {
                if (isset($days[$f['date']])) {
                    $days[$f['date']]['events'][] = $f['name'].($f['religion'] !== 'hindu' ? ' ('.Festivals::RELIGIONS[$f['religion']].')' : '');
                }
            }
        } catch (RuntimeException $e) {
            return $this->engineDown($e);
        }

        return response()->json(['data' => collect($days)->map(fn ($x, $date) => ['date' => $date, 'tithi' => $x['tithi'], 'nakshatra' => $x['nakshatra'], 'events' => $x['events']])->values(),
            'meta' => ['month' => $month->format('Y-m'), 'place' => ['id' => $place->id, 'name' => $place->name]]]);
    }

    /** Zodiac sign guide (same as /rashi/{sign}). */
    public function rashi(Request $request, string $sign, Gochar $gochar): JsonResponse
    {
        $i = array_search($sign, ContentController::signSlugs(), true);
        abort_if($i === false, 404);
        $locale = $request->query('lang') === 'en' ? 'en' : 'hi';
        $today = now('Asia/Kolkata')->toDateString();
        try {
            $now = Cache::remember('gochar:now:'.now()->format('YmdH'), 3600, fn () => $gochar->now());
        } catch (RuntimeException $e) {
            return $this->engineDown($e);
        }
        $lord = Vedic::SIGN_LORDS[$i];
        $elements = ['Fire', 'Earth', 'Air', 'Water'];
        $qualities = ['Movable (chara)', 'Fixed (sthira)', 'Dual (dwiswabhava)'];

        return response()->json(['data' => [
            'sign' => $i, 'slug' => $sign, 'name' => Vedic::SIGNS[$i], 'hi' => ContentController::SIGNS_HI[$i], 'symbol' => ContentController::SIGN_SYMBOLS[$i],
            'lord' => ['planet' => $lord, 'name' => Vedic::PLANET_NAMES[$lord]], 'element' => $elements[$i % 4], 'quality' => $qualities[$i % 3],
            'nature' => $i % 2 === 0 ? 'Odd sign (masculine, krura)' : 'Even sign (feminine, saumya)',
            'nakshatras' => collect(ContentController::nakshatrasIn($i))->map(fn ($p, $n) => ['name' => $n, 'padas' => $p])->values(),
            'lord_day' => Gochar::FACTS[$lord]['day'], 'lord_gem' => Gochar::FACTS[$lord]['gem'],
            'transits' => collect($now)->map(fn ($pos, $p) => ['planet' => $p, 'name' => Vedic::PLANET_NAMES[$p], 'sign_name' => Vedic::SIGNS[$pos['sign']],
                'house' => Gochar::fromMoon($p, $pos['sign'])[$i]['house'], 'favourable' => Gochar::fromMoon($p, $pos['sign'])[$i]['good']])->values(),
            'horoscopes' => DB::table('horoscopes')->where('status', 'published')->where('kind', 'rashi')->where('sign', $i)->where('locale', $locale)
                ->where('starts_on', '<=', $today)->where('ends_on', '>=', $today)->orderBy('topic')->orderBy('period')->get()
                ->map(fn ($r) => ['period' => $r->period, 'topic' => $r->topic, 'body' => $r->body])->values(),
        ]]);
    }

    /** The nine planets guide (same as /navagraha). */
    public function navagraha(Gochar $gochar): JsonResponse
    {
        try {
            $now = Cache::remember('gochar:now:'.now()->format('YmdH'), 3600, fn () => $gochar->now());
        } catch (RuntimeException $e) {
            return $this->engineDown($e);
        }

        return response()->json(['data' => collect(Gochar::PLANETS)->map(fn ($p) => ['planet' => $p, 'name' => Vedic::PLANET_NAMES[$p], 'hi' => Gochar::HI[$p],
            'now' => ['sign_name' => Vedic::SIGNS[$now[$p]['sign']], 'degree' => $now[$p]['deg'], 'retro' => $now[$p]['retro']],
            'rules' => array_map(fn ($s) => Vedic::SIGNS[$s], Gochar::ownSigns($p)), 'exalted' => Vedic::SIGNS[Gochar::FACTS[$p]['exalt'][0]], 'debilitated' => Vedic::SIGNS[Gochar::FACTS[$p]['debil']],
            'nakshatras' => Gochar::nakshatras($p), 'dasha_years' => Vedic::DASHA_YEARS[$p]] + array_intersect_key(Gochar::FACTS[$p], array_flip(['signifies', 'day', 'gem', 'colour', 'stay'])))->values()]);
    }

    public function faq(): JsonResponse
    {
        $faqs = DB::table('faqs')->where('published', true)->where('locale', app()->getLocale())->orderBy('sort')->get(['question', 'answer']);
        if ($faqs->isEmpty()) {
            $faqs = DB::table('faqs')->where('published', true)->where('locale', 'en')->orderBy('sort')->get(['question', 'answer']);
        }

        return response()->json(['data' => $faqs]);
    }

    /** Business facts for the app's About and Contact screens. */
    public function site(Business $business): JsonResponse
    {
        return response()->json(['data' => $business->toApi()]);
    }
}
