<?php

namespace App\Domain\Astro;

use App\Domain\Astro\Time\LocalTimeResolver;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Free calculators. Every tool declares its inputs and a category label shown to the user:
 *  astronomical — computed positions/times (Swiss Ephemeris)
 *  traditional  — classical Jyotish rules applied to computed positions
 *  symbolic     — numerology/colour/gem correspondences (tradition, not measurement)
 *  entertainment — games (FLAMES, love calculator); no predictive claim
 */
class Tools
{
    public const TOOLS = [
        'kundli' => ['Free Kundli', 'birth', 'traditional', 'Birth chart (D1), Navamsa (D9), planets, houses and Vimshottari dasha.'],
        'kundli-matching' => ['Kundli Matching', 'couple', 'traditional', 'Ashtakoota 36-guna milan with Manglik check for both partners.'],
        'moon-sign' => ['Moon Sign', 'birth', 'astronomical', 'Your Rashi — the sign the Moon occupied at birth.'],
        'nakshatra' => ['Nakshatra', 'birth', 'astronomical', 'Your birth star and pada.'],
        'lagna-navamsa' => ['Lagna & Navamsa', 'birth', 'astronomical', 'Ascendant and the Navamsa (D9) chart.'],
        'lal-kitab' => ['Lal Kitab Chart', 'birth', 'traditional', 'Planets in the fixed-house Lal Kitab chart.'],
        'sade-sati' => ['Sade Sati', 'birth', 'traditional', "Saturn's 7½-year periods over your Moon sign."],
        'pitra-dosh' => ['Pitra Dosh', 'birth', 'traditional', 'Classical indicators involving the Sun, 9th house, Rahu and Saturn.'],
        'rahu-ketu' => ['Rahu–Ketu', 'birth', 'traditional', 'Natal nodes by house and their current transit.'],
        'numerology' => ['Numerology', 'name_dob', 'symbolic', 'Mulank, Bhagyank and name number together.'],
        'name-number' => ['Name Number', 'name', 'symbolic', 'Chaldean value of your name.'],
        'destiny-number' => ['Destiny Number (Bhagyank)', 'dob', 'symbolic', 'From your full date of birth.'],
        'lo-shu-grid' => ['Lo Shu Grid', 'dob', 'symbolic', 'Numbers present and missing in your date of birth.'],
        'personal-year' => ['Personal Year', 'dob', 'symbolic', 'Your numerology cycle for a chosen year.'],
        'favourable-alphabets' => ['Favourable Alphabets & Numbers', 'dob', 'symbolic', 'Letters and numbers traditionally friendly to your Mulank.'],
        'memorable-periods' => ['Memorable Periods of Life', 'birth', 'traditional', 'Your Vimshottari Mahadasha and Antardasha timeline.'],
        'predictive-personality' => ['Personality by Lagna & Moon', 'birth', 'traditional', 'Classical qualities of your ascendant and Moon sign.'],
        'baby-name' => ['Baby Name Letters', 'birth', 'traditional', 'Starting syllables from the Moon nakshatra pada (Avakahada chakra).'],
        'lucky-colour' => ['Lucky Colours', 'dob', 'symbolic', 'Colours associated with your Mulank.'],
        'unlucky-colour' => ['Colours to Use Less', 'dob', 'symbolic', 'Colours traditionally less suited to your Mulank.'],
        'lucky-dates' => ['Favourable Dates', 'dob_month', 'symbolic', 'Dates in a month friendly to your Mulank.'],
        'unlucky-dates' => ['Less Favourable Dates', 'dob_month', 'symbolic', 'Dates in a month traditionally less suited to your Mulank.'],
        'vehicle-number' => ['Vehicle Number', 'vehicle', 'symbolic', 'Numerology of a registration number against your Mulank.'],
        'rudraksha' => ['Rudraksha Suggestion', 'birth', 'traditional', 'Traditional mukhi for your Lagna lord.'],
        'love-calculator' => ['Love Calculator', 'two_names', 'entertainment', 'A light-hearted name-number game. Not a prediction.'],
        'flames' => ['FLAMES', 'two_names', 'entertainment', 'The classic name game. Just for fun.'],
    ];

    /** Avakahada chakra starting syllables, 4 per nakshatra (common North Indian list; variants exist). */
    public const SYLLABLES = [
        ['Chu', 'Che', 'Cho', 'La'], ['Li', 'Lu', 'Le', 'Lo'], ['A', 'I', 'U', 'E'], ['O', 'Va', 'Vi', 'Vu'], ['Ve', 'Vo', 'Ka', 'Ki'],
        ['Ku', 'Gha', 'Na', 'Chha'], ['Ke', 'Ko', 'Ha', 'Hi'], ['Hu', 'He', 'Ho', 'Da'], ['Di', 'Du', 'De', 'Do'], ['Ma', 'Mi', 'Mu', 'Me'],
        ['Mo', 'Ta', 'Ti', 'Tu'], ['Te', 'To', 'Pa', 'Pi'], ['Pu', 'Sha', 'Na', 'Tha'], ['Pe', 'Po', 'Ra', 'Ri'], ['Ru', 'Re', 'Ro', 'Ta'],
        ['Ti', 'Tu', 'Te', 'To'], ['Na', 'Ni', 'Nu', 'Ne'], ['No', 'Ya', 'Yi', 'Yu'], ['Ye', 'Yo', 'Bha', 'Bhi'], ['Bhu', 'Dha', 'Pha', 'Dha'],
        ['Bhe', 'Bho', 'Ja', 'Ji'], ['Khi', 'Khu', 'Khe', 'Kho'], ['Ga', 'Gi', 'Gu', 'Ge'], ['Go', 'Sa', 'Si', 'Su'], ['Se', 'So', 'Da', 'Di'],
        ['Du', 'Tha', 'Jha', 'Na'], ['De', 'Do', 'Cha', 'Chi'],
    ];

    /** Classical sign qualities: element, modality, lord. */
    public const SIGN_QUALITIES = [
        ['Fire', 'Movable (chara)'], ['Earth', 'Fixed (sthira)'], ['Air', 'Dual (dvisvabhava)'], ['Water', 'Movable (chara)'],
        ['Fire', 'Fixed (sthira)'], ['Earth', 'Dual (dvisvabhava)'], ['Air', 'Movable (chara)'], ['Water', 'Fixed (sthira)'],
        ['Fire', 'Dual (dvisvabhava)'], ['Earth', 'Movable (chara)'], ['Air', 'Fixed (sthira)'], ['Water', 'Dual (dvisvabhava)'],
    ];

    public const ELEMENT_NOTE = [
        'Fire' => 'In the tradition, fire signs are linked with initiative, energy and leadership.',
        'Earth' => 'Earth signs are linked with practicality, patience and steadiness.',
        'Air' => 'Air signs are linked with ideas, communication and relationships.',
        'Water' => 'Water signs are linked with feeling, intuition and care for others.',
    ];
    public const MODALITY_NOTE = [
        'Movable (chara)' => 'Movable signs favour change, travel and starting things.',
        'Fixed (sthira)' => 'Fixed signs favour persistence and finishing what is begun.',
        'Dual (dvisvabhava)' => 'Dual signs favour adaptability and learning.',
    ];

    public const RUDRAKSHA = ['sun' => '1 or 12 mukhi', 'moon' => '2 mukhi', 'mars' => '3 mukhi', 'mercury' => '4 mukhi', 'jupiter' => '5 mukhi',
        'venus' => '6 mukhi', 'saturn' => '7 mukhi', 'rahu' => '8 mukhi', 'ketu' => '9 mukhi'];

    public function __construct(private KundliService $kundli, private LocalTimeResolver $time, private Engine $engine) {}

    public static function rules(string $input): array
    {
        $person = fn (string $p = '') => [
            $p.'name' => ['nullable', 'string', 'max:120'],
            $p.'birth_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.config('astro.min_year').'-01-01', 'before_or_equal:'.config('astro.max_year').'-12-31'],
            $p.'birth_time' => ['required', 'date_format:H:i'],
            $p.'place_id' => ['required', 'integer', 'exists:places,id'],
        ];

        return match ($input) {
            'birth' => $person(),
            'couple' => $person('a_') + $person('b_'),
            'name' => ['name' => ['required', 'string', 'max:120', 'regex:/[A-Za-z]/']],
            'dob' => ['birth_date' => ['required', 'date_format:Y-m-d'], 'year' => ['nullable', 'integer', 'min:1900', 'max:2200']],
            'dob_month' => ['birth_date' => ['required', 'date_format:Y-m-d'], 'month' => ['required', 'date_format:Y-m']],
            'name_dob' => ['name' => ['required', 'string', 'max:120', 'regex:/[A-Za-z]/'], 'birth_date' => ['required', 'date_format:Y-m-d']],
            'vehicle' => ['birth_date' => ['required', 'date_format:Y-m-d'], 'plate' => ['required', 'string', 'max:20', 'regex:/\d/']],
            'two_names' => ['name' => ['required', 'string', 'max:60', 'regex:/[A-Za-z]/'], 'partner' => ['required', 'string', 'max:60', 'regex:/[A-Za-z]/']],
        };
    }

    public function compute(string $slug, array $in): array
    {
        [$title, $input, $kind] = self::TOOLS[$slug];
        $out = ['tool' => $slug, 'title' => $title, 'kind' => $kind];
        if ($input === 'birth') {
            $c = $this->chartFor($in);
            $out['chart'] = $c;
        }
        $dob = $in['birth_date'] ?? null;
        $mulank = $dob ? Numerology::mulank((int) substr($dob, 8, 2)) : null;

        return $out + match ($slug) {
            'kundli', 'moon-sign', 'nakshatra', 'lagna-navamsa', 'memorable-periods' => [],
            'kundli-matching' => $this->matching($in),
            'lal-kitab' => ['lal_kitab' => $this->lalKitab($out['chart'])],
            'sade-sati' => ['sade_sati' => $this->kundli->sadeSati($out['chart']['moon_sign']['sign'], new DateTimeImmutable($out['chart']['input']['utc']), 90)],
            'pitra-dosh' => ['pitra' => $this->pitra($out['chart'])],
            'rahu-ketu' => ['nodes' => $this->nodes($out['chart'])],
            'predictive-personality' => ['personality' => $this->personality($out['chart'])],
            'baby-name' => ['syllables' => self::SYLLABLES[$out['chart']['nakshatra']['index']], 'pada_syllable' => self::SYLLABLES[$out['chart']['nakshatra']['index']][$out['chart']['nakshatra']['pada'] - 1]],
            'rudraksha' => ['rudraksha' => ['lagna_lord' => Vedic::SIGN_LORDS[$out['chart']['lagna']['sign']], 'mukhi' => self::RUDRAKSHA[Vedic::SIGN_LORDS[$out['chart']['lagna']['sign']]]]],
            'numerology' => ['mulank' => $mulank, 'bhagyank' => Numerology::bhagyank($dob), 'name_number' => Numerology::nameNumber($in['name']), 'traits' => Numerology::TRAITS[$mulank]],
            'name-number' => ['name_number' => Numerology::nameNumber($in['name'])],
            'destiny-number' => ['bhagyank' => Numerology::bhagyank($dob), 'mulank' => $mulank],
            'lo-shu-grid' => ['loshu' => Numerology::loShu($dob)],
            'personal-year' => ['year' => $y = (int) ($in['year'] ?? now('Asia/Kolkata')->year), 'personal_year' => Numerology::personalYear((int) substr($dob, 8, 2), (int) substr($dob, 5, 2), $y)],
            'favourable-alphabets' => ['mulank' => $mulank, 'traits' => Numerology::TRAITS[$mulank], 'letters' => $this->letters($mulank)],
            'lucky-colour', 'unlucky-colour' => ['mulank' => $mulank, 'traits' => Numerology::TRAITS[$mulank]],
            'lucky-dates', 'unlucky-dates' => ['mulank' => $mulank, 'month' => $in['month'], 'dates' => Numerology::dates($mulank, (int) substr($in['month'], 0, 4), (int) substr($in['month'], 5, 2))],
            'vehicle-number' => ['mulank' => $mulank, 'vehicle' => Numerology::vehicle($in['plate'], $mulank), 'plate' => strtoupper($in['plate'])],
            'love-calculator' => $this->love($in['name'], $in['partner']),
            'flames' => ['flames' => Numerology::flames($in['name'], $in['partner'])],
        };
    }

    public function chartFor(array $in, string $p = ''): array
    {
        $place = DB::table('places')->where('id', $in[$p.'place_id'])->first();
        try {
            $r = $this->time->resolve($in[$p.'birth_date'], $in[$p.'birth_time'], $place->tz_name);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([$p.'birth_time' => $e->getMessage()]);
        }
        $c = $this->kundli->chart($r['utc'], (float) $place->lat, (float) $place->lng, $place->tz_name);
        $c['input']['place'] = implode(', ', array_filter([$place->name, $place->admin1, $place->country]));
        $c['input']['name'] = $in[$p.'name'] ?? null;
        $c['input']['tz_resolution'] = $r['resolution'];

        return $c;
    }

    private function matching(array $in): array
    {
        $a = $this->chartFor($in, 'a_');
        $b = $this->chartFor($in, 'b_');
        $pick = fn ($c) => ['nak' => $c['nakshatra']['index'], 'sign' => $c['moon_sign']['sign'], 'moon_lon' => $c['planets']['moon']['lon']];

        return ['a' => $a, 'b' => $b, 'match' => Matching::ashtakoota($pick($a), $pick($b))];
    }

    private function lalKitab(array $c): array
    {
        // Lal Kitab uses a fixed chart: Aries is always house 1; planets keep their signs.
        $h = array_fill(1, 12, []);
        foreach ($c['planets'] as $k => $p) {
            if ($p['classical']) {
                $h[$p['sign'] + 1][] = $k;
            }
        }

        return $h;
    }

    private function pitra(array $c): array
    {
        $p = $c['planets'];
        $ninthSign = ($c['lagna']['sign'] + 8) % 12;
        $ninthLord = Vedic::SIGN_LORDS[$ninthSign];
        $with = fn (string $x, array $ys) => array_values(array_filter($ys, fn ($y) => $p[$x]['sign'] === $p[$y]['sign']));
        $ind = [];
        if ($w = $with('sun', ['rahu', 'ketu', 'saturn'])) {
            $ind[] = 'Sun is in the same sign as '.implode(' and ', array_map('ucfirst', $w)).'.';
        }
        $in9 = array_values(array_filter(['rahu', 'ketu', 'saturn'], fn ($y) => $p[$y]['sign'] === $ninthSign));
        if ($in9) {
            $ind[] = implode(' and ', array_map('ucfirst', $in9)).' in the 9th house.';
        }
        if ($ninthLord !== 'rahu' && ($w = $with($ninthLord, ['rahu', 'ketu']))) {
            $ind[] = '9th lord '.ucfirst($ninthLord).' is with '.implode(' and ', array_map('ucfirst', $w)).'.';
        }

        return ['indicators' => $ind, 'ninth_lord' => $ninthLord];
    }

    private function nodes(array $c): array
    {
        $now = Engine::jd(new DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $t = $this->engine->chart($now, 0, 0);
        $next = $this->engine->signIngresses('rahu', $now, $now + 600);

        return ['natal' => ['rahu' => $c['planets']['rahu'], 'ketu' => $c['planets']['ketu']],
            'transit' => ['rahu' => Vedic::SIGNS[Vedic::sign($t['planets']['rahu']['lon'])], 'ketu' => Vedic::SIGNS[Vedic::sign($t['planets']['ketu']['lon'])],
                'rahu_house_from_lagna' => ((Vedic::sign($t['planets']['rahu']['lon']) - $c['lagna']['sign'] + 12) % 12) + 1,
                'rahu_house_from_moon' => ((Vedic::sign($t['planets']['rahu']['lon']) - $c['moon_sign']['sign'] + 12) % 12) + 1,
                'next_change' => $next ? Engine::fromJd($next[0]['jd'])->format('Y-m-d') : null]];
    }

    private function personality(array $c): array
    {
        $f = function (int $sign) {
            [$el, $mod] = self::SIGN_QUALITIES[$sign];

            return ['sign' => Vedic::SIGNS[$sign], 'element' => $el, 'modality' => $mod, 'lord' => Vedic::PLANET_NAMES[Vedic::SIGN_LORDS[$sign]], 'notes' => [self::ELEMENT_NOTE[$el], self::MODALITY_NOTE[$mod]]];
        };

        return ['lagna' => $f($c['lagna']['sign']), 'moon' => $f($c['moon_sign']['sign'])];
    }

    private function letters(int $mulank): array
    {
        $friendly = Numerology::TRAITS[$mulank]['friendly'];

        return array_keys(array_filter(Numerology::CHALDEAN, fn ($v) => in_array($v, $friendly, true)));
    }

    private function love(string $a, string $b): array
    {
        $na = Numerology::nameNumber($a)['number'];
        $nb = Numerology::nameNumber($b)['number'];
        $rel = in_array($nb, Numerology::TRAITS[$na]['friendly'], true) ? 'friendly' : (in_array($nb, Numerology::TRAITS[$na]['unfriendly'], true) ? 'challenging' : 'neutral');

        return ['love' => ['a' => $na, 'b' => $nb, 'relation' => $rel]];
    }
}
