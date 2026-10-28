<?php

namespace Tests\Feature\Astro;

use App\Domain\Astro\Engine;
use App\Domain\Astro\KundliService;
use App\Domain\Astro\Matching;
use App\Domain\Astro\Numerology;
use App\Domain\Astro\PanchangService;
use App\Domain\Astro\Vedic;
use DateTimeImmutable;
use DateTimeZone;
use Tests\TestCase;

/** Regression fixtures for the astronomy engine and traditional rules. Sources: docs/astro/methods.md. */
class EngineFixturesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! app(Engine::class)->available()) {
            $this->markTestSkipped('engine not built (run engine/build.sh)');
        }
    }

    private function utc(string $s): DateTimeImmutable
    {
        return new DateTimeImmutable($s, new DateTimeZone('UTC'));
    }

    public function test_positions_match_swetest_reference(): void
    {
        // Reference: swetest -b1.1.2000 -ut12:00 -sid1 (Lahiri) — Swiss Ephemeris 2.10.03.
        $c = app(Engine::class)->chart(2451545.0, 25.594, 85.1356);
        $this->assertSame('swiss', $c['ephemeris']);
        foreach (['sun' => 256.5156962, 'moon' => 199.4705290, 'mars' => 304.1100800, 'mercury' => 248.0360546, 'jupiter' => 1.3998647, 'venus' => 217.7125659, 'saturn' => 16.5424410, 'rahu' => 101.1874236] as $p => $lon) {
            $this->assertEqualsWithDelta($lon, $c['planets'][$p]['lon'], 1e-6, $p);
        }
        $this->assertEqualsWithDelta(norm360(101.1874236 + 180), $c['planets']['ketu']['lon'], 1e-6);
        $this->assertTrue($c['planets']['saturn']['retro']);
        $this->assertEqualsWithDelta(23.857, $c['ayanamsha'], 0.001); // Lahiri ayanamsha at J2000 ≈ 23°51′
    }

    public function test_known_sidereal_ingresses_2025(): void
    {
        $e = app(Engine::class);
        $from = Engine::jd($this->utc('2025-01-01'));
        $to = Engine::jd($this->utc('2025-12-31'));
        $sat = collect($e->signIngresses('saturn', $from, $to))->firstWhere('sign', 11); // Meena
        $this->assertSame('2025-03-29', Engine::fromJd($sat['jd'])->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('Y-m-d'));
        $jup = collect($e->signIngresses('jupiter', $from, $to))->firstWhere('sign', 2); // Mithuna
        $this->assertSame('2025-05-14', Engine::fromJd($jup['jd'])->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('Y-m-d'));
        $rahu = collect($e->signIngresses('rahu', $from, $to))->firstWhere('sign', 10); // Kumbha (mean node)
        $this->assertSame('2025-05-18', Engine::fromJd($rahu['jd'])->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('Y-m-d'));
        $this->assertTrue($rahu['retro']);
    }

    public function test_sade_sati_for_mesha_moon_starts_with_saturn_in_meena(): void
    {
        $periods = app(KundliService::class)->sadeSati(0, $this->utc('2020-01-01'), 15);
        $this->assertNotEmpty($periods);
        $this->assertContains($periods[0]['start'], ['2025-03-28', '2025-03-29']);
        $this->assertSame('rising', $periods[0]['phases'][0]['phase']);
    }

    public function test_kundli_structure_dasha_and_signs(): void
    {
        $c = app(KundliService::class)->chart($this->utc('1990-05-14 01:00:00'), 25.59408, 85.13563, 'Asia/Kolkata');
        $this->assertSame('1990-05-14 06:30', $c['input']['local']);
        $this->assertCount(12, $c['houses']);
        $this->assertSame(9, array_sum(array_map(fn ($h) => count($h['planets']), $c['houses'])));
        $this->assertSame(9, array_sum(array_map(fn ($h) => count($h['planets']), $c['navamsa'])));
        $this->assertSame(7, ($c['planets']['ketu']['house'] - $c['planets']['rahu']['house'] + 12) % 12 + 1); // Ketu 7th from Rahu
        // Dasha periods are contiguous and total 120 years.
        $p = $c['dasha']['periods'];
        $this->assertCount(9, $p);
        for ($i = 1; $i < 9; $i++) {
            $this->assertSame($p[$i - 1]['end'], $p[$i]['start']);
        }
        $this->assertEqualsWithDelta(120, (strtotime($p[8]['end']) - strtotime($p[0]['start'])) / (365.25 * 86400), 0.01);
        $this->assertSame($c['nakshatra']['lord'], $p[0]['lord']);
    }

    public function test_vimshottari_balance_at_nakshatra_start(): void
    {
        $k = app(KundliService::class);
        $this->assertEqualsWithDelta(7.0, $k->vimshottari(0.0001, $this->utc('2000-01-01'))['balance_years'], 0.01); // Ashwini → Ketu 7 y
        $this->assertSame('venus', $k->vimshottari(14.0, $this->utc('2000-01-01'))['periods'][0]['lord']);            // Bharani → Venus
        $this->assertEqualsWithDelta(10.0, $k->vimshottari(360 / 27 * 1.5, $this->utc('2000-01-01'))['balance_years'], 0.01); // mid-Bharani → 10 of 20 years left
    }

    public function test_panchang_patna_and_high_latitude(): void
    {
        $p = app(PanchangService::class)->day('2026-10-28', 25.59408, 85.13563, 'Asia/Kolkata');
        $sr = new DateTimeImmutable($p['sunrise']);
        $this->assertSame('2026-10-28', $sr->format('Y-m-d'));
        $this->assertGreaterThanOrEqual('05:45', $sr->format('H:i'));
        $this->assertLessThanOrEqual('06:05', $sr->format('H:i'));
        $this->assertSame('Budhavara (Wednesday)', $p['vara']);
        foreach (['tithi', 'nakshatra', 'yoga', 'karana'] as $a) {
            $this->assertNotEmpty($p['angas'][$a][0]['name']);
        }
        $this->assertFalse($p['polar']);
        $this->assertLessThan($p['intervals']['rahu_kaal']['end'], $p['intervals']['rahu_kaal']['start']);
        // Wednesday Rahu Kaal is the 5th eighth of daytime: sunrise + 4/8 of day length.
        $day = (new DateTimeImmutable($p['sunset']))->getTimestamp() - $sr->getTimestamp();
        $this->assertEqualsWithDelta($sr->getTimestamp() + $day * 4 / 8, (new DateTimeImmutable($p['intervals']['rahu_kaal']['start']))->getTimestamp(), 2);
        $this->assertSame('11', (new DateTimeImmutable($p['intervals']['rahu_kaal']['start']))->format('H'));

        $polar = app(PanchangService::class)->day('2026-06-21', 78.2232, 15.6267, 'Arctic/Longyearbyen');
        $this->assertTrue($polar['polar']);
        $this->assertSame([], $polar['intervals']);
        $this->assertNotEmpty($polar['angas']['tithi'][0]['name']);
    }

    public function test_matching_rules(): void
    {
        $same = ['nak' => 0, 'sign' => 0, 'moon_lon' => 5.0];
        $m = Matching::ashtakoota($same, $same);
        $this->assertSame(0, $m['kootas']['nadi']['score']);   // same nadi
        $this->assertSame(7, $m['kootas']['bhakoot']['score']); // 1/1
        $this->assertSame(6, $m['kootas']['gana']['score']);   // Deva–Deva
        $this->assertSame(4, $m['kootas']['yoni']['score']);   // same yoni
        $this->assertSame(5.0, (float) $m['kootas']['graha_maitri']['score']);
        $this->assertContains('Nadi dosha', $m['doshas']);
        $m2 = Matching::ashtakoota(['nak' => 3, 'sign' => 1, 'moon_lon' => 45.0], ['nak' => 21, 'sign' => 9, 'moon_lon' => 285.0]);
        $this->assertGreaterThanOrEqual(0, $m2['total']);
        $this->assertLessThanOrEqual(36, $m2['total']);
        foreach ($m2['kootas'] as $k) {
            $this->assertLessThanOrEqual($k['max'], $k['score']);
        }
    }

    public function test_numerology(): void
    {
        $this->assertSame(2, Numerology::mulank(29));
        $this->assertSame(2, Numerology::bhagyank('1990-05-14'));     // 29 → 11 → 2
        $this->assertSame(1, Numerology::nameNumber('Rajnath')['number']); // 2+1+1+5+1+4+5 = 19 → 1
        $g = Numerology::loShu('1990-05-14');
        $this->assertSame([2, 3, 6, 7, 8], $g['missing']);
        $this->assertSame(2, $g['counts'][9]);
        $this->assertSame(Numerology::reduce(14 % 9 ?: 9) , Numerology::reduce(5));
        $this->assertContains(Numerology::flames('Rama', 'Sita'), ['Friends', 'Love', 'Affection', 'Marriage', 'Enemies', 'Siblings']);
        $this->assertSame(Numerology::flames('Rama', 'Sita'), Numerology::flames('Rama', 'Sita'));
        $this->assertSame('favourable', Numerology::vehicle('BR01 AB 1234', 1)['verdict']); // 0+1+1+2+3+4 = 11 → 2, friendly to 1
    }

    public function test_tithi_and_karana_names(): void
    {
        $this->assertSame('Purnima', Vedic::tithiName(14));
        $this->assertSame('Amavasya', Vedic::tithiName(29));
        $this->assertSame('Ekadashi', Vedic::tithiName(10));
        $this->assertSame('Ekadashi', Vedic::tithiName(25));
        $this->assertSame('Kimstughna', Vedic::karanaName(0));
        $this->assertSame('Bava', Vedic::karanaName(1));
        $this->assertSame('Naga', Vedic::karanaName(59));
    }
}

function norm360(float $x): float
{
    $x = fmod($x, 360);

    return $x < 0 ? $x + 360 : $x;
}
