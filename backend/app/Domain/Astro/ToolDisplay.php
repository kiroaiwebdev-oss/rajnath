<?php

namespace App\Domain\Astro;

use Carbon\CarbonImmutable;

/**
 * Display-ready summary of a calculator result for the app (same content as the website's
 * tools/_result.blade.php). Shape: list of sections, each
 *   {title?: string, highlight?: string, rows?: list<[label, value]>, items?: list<string>,
 *    table?: {head: list<string>, rows: list<list<string>>}, text?: string}
 * plus a closing note.
 */
final class ToolDisplay
{
    /** @return array{sections: list<array>, note: ?string} */
    public static function for(array $r): array
    {
        $c = $r['chart'] ?? null;
        $s = [];
        $V = Vedic::class;
        if ($c) {
            $s[] = ['title' => 'Birth details', 'text' => trim(($c['input']['name'] ? $c['input']['name'].' · ' : '').CarbonImmutable::parse($c['input']['local'])->format('d M Y, g:i A').' · '.$c['input']['place']),
                'rows' => [['Lagna (Ascendant)', $c['lagna']['sign_name'].' '.$c['lagna']['degree']], ['Moon sign (Rashi)', $c['moon_sign']['name']], ['Nakshatra', $c['nakshatra']['name'].', pada '.$c['nakshatra']['pada']]]];
        }
        $planets = fn () => ['title' => 'Planet positions', 'table' => ['head' => ['Planet', 'Sign', 'Degree', 'House', 'Nakshatra', 'Navamsa'],
            'rows' => collect($c['planets'])->filter(fn ($p) => $p['classical'])->map(fn ($p, $k) => [$p['name'].($p['retro'] && ! in_array($k, ['rahu', 'ketu'], true) ? ' (R)' : ''), $p['sign_name'], $p['degree'], (string) $p['house'], $p['nakshatra'].' '.$p['pada'], $V::SIGNS[$p['navamsa_sign']]])->values()->all()]];
        $dasha = fn () => ['title' => 'Vimshottari dasha', 'table' => ['head' => ['Mahadasha', 'From', 'To'],
            'rows' => array_map(fn ($p) => [$V::PLANET_NAMES[$p['lord']] ?? $p['lord'], substr((string) $p['start'], 0, 10), substr((string) $p['end'], 0, 10)], $c['dasha']['periods'] ?? [])]];

        switch ($r['tool']) {
            case 'kundli':
            case 'lagna-navamsa':
                $s[] = $planets();
                $s[] = ['title' => 'Navamsa (D9) Lagna', 'text' => $V::SIGNS[$c['lagna']['navamsa_sign']]];
                if ($r['tool'] === 'kundli') {
                    $m = $c['manglik'];
                    $s[] = ['title' => 'Manglik check', 'highlight' => $m['is_manglik'] ? 'Manglik (basic rule)' : 'Not Manglik (basic rule)',
                        'text' => 'Mars is in house '.$m['mars_house_from']['lagna'].' from Lagna, '.$m['mars_house_from']['moon'].' from the Moon and '.$m['mars_house_from']['venus'].' from Venus. Cancellation rules are not applied automatically — ask Acharya Ji for a full assessment.'];
                    $s[] = $dasha();
                }
                break;
            case 'memorable-periods':
                $s[] = $dasha();
                break;
            case 'moon-sign':
                $s[] = ['highlight' => $c['moon_sign']['name'], 'text' => 'Its lord is '.$V::PLANET_NAMES[$V::SIGN_LORDS[$c['moon_sign']['sign']]].'.'];
                break;
            case 'nakshatra':
                $s[] = ['highlight' => $c['nakshatra']['name'].', pada '.$c['nakshatra']['pada'], 'text' => 'Nakshatra lord: '.$V::PLANET_NAMES[$c['nakshatra']['lord']].' (this sets your first Vimshottari dasha).'];
                break;
            case 'kundli-matching':
                $m = $r['match'];
                $s[] = ['highlight' => rtrim(rtrim(number_format($m['total'], 1), '0'), '.').' / 36 gunas', 'text' => 'Traditionally '.$m['band'].'.'.($m['doshas'] ? ' Doshas present: '.implode(', ', $m['doshas']).'.' : '')];
                $s[] = ['title' => 'Koota scores', 'table' => ['head' => ['Koota', 'Details', 'Score'], 'rows' => collect($m['kootas'])->map(fn ($v, $k) => [ucwords(str_replace('_', ' ', $k)), $v['detail'], $v['score'].' / '.$v['max']])->values()->all()]];
                $s[] = ['rows' => [['Manglik (boy)', $r['a']['manglik']['is_manglik'] ? 'Yes' : 'No'], ['Manglik (girl)', $r['b']['manglik']['is_manglik'] ? 'Yes' : 'No']],
                    'text' => 'Guna milan is one traditional input. Exceptions (parihara), the 7th house and Navamsa should be reviewed by an astrologer.'];
                break;
            case 'lal-kitab':
                $s[] = ['title' => 'Lal Kitab chart (Aries = house 1)', 'rows' => collect($r['lal_kitab'])->map(fn ($pl, $i) => ['House '.$i, $pl ? implode(', ', array_map(fn ($p) => $V::PLANET_NAMES[$p] ?? ucfirst($p), $pl)) : '—'])->values()->all()];
                break;
            case 'sade-sati':
                $now = now()->format('Y-m-d');
                $s[] = ['title' => 'Sade Sati periods for Moon sign '.$c['moon_sign']['name'], 'items' => $r['sade_sati']
                    ? array_map(fn ($p) => CarbonImmutable::parse($p['start'])->format('d M Y').' – '.CarbonImmutable::parse($p['end'])->format('d M Y').($p['start'] <= $now && $now <= $p['end'] ? ' (now)' : '')
                        .' · '.implode(', ', array_map(fn ($ph) => ucfirst($ph['phase']).' '.CarbonImmutable::parse($ph['start'])->format('M Y').'–'.CarbonImmutable::parse($ph['end'])->format('M Y'), $p['phases'])), $r['sade_sati'])
                    : ['No Sade Sati in the 90 years from birth.'],
                    'text' => 'Phases: rising (Saturn in the 12th from the Moon), peak (over the Moon), setting (2nd from the Moon).'];
                break;
            case 'pitra-dosh':
                $s[] = $r['pitra']['indicators'] ? ['title' => 'Traditional indicators found', 'items' => $r['pitra']['indicators']]
                    : ['text' => 'None of the common classical indicators were found (Sun with Rahu/Ketu/Saturn, Rahu/Ketu/Saturn in the 9th house, 9th lord with the nodes).'];
                $s[] = ['text' => 'Pitra dosh is assessed differently across traditions. This check is a starting point, not a verdict.'];
                break;
            case 'rahu-ketu':
                $n = $r['nodes'];
                $s[] = ['rows' => [['Natal Rahu', $n['natal']['rahu']['sign_name'].', house '.$n['natal']['rahu']['house']], ['Natal Ketu', $n['natal']['ketu']['sign_name'].', house '.$n['natal']['ketu']['house']],
                    ['Rahu now', $n['transit']['rahu'].' ('.$n['transit']['rahu_house_from_lagna'].'th from Lagna, '.$n['transit']['rahu_house_from_moon'].'th from Moon)'], ['Ketu now', $n['transit']['ketu']],
                    ['Next sign change', $n['transit']['next_change'] ? CarbonImmutable::parse($n['transit']['next_change'])->format('d M Y') : '—']]];
                break;
            case 'predictive-personality':
                foreach (['lagna' => 'Lagna (how you meet the world)', 'moon' => 'Moon sign (your mind and feelings)'] as $k => $h) {
                    $x = $r['personality'][$k];
                    $s[] = ['title' => $h, 'highlight' => $x['sign'], 'text' => $x['element'].' · '.$x['modality'].' · lord '.$x['lord'], 'items' => $x['notes']];
                }
                break;
            case 'baby-name':
                $s[] = ['highlight' => $r['pada_syllable'], 'text' => 'Traditional starting sound for '.$c['nakshatra']['name'].' pada '.$c['nakshatra']['pada'].'. All four syllables of this nakshatra: '.implode(', ', $r['syllables']).'.'];
                break;
            case 'rudraksha':
                $s[] = ['highlight' => $r['rudraksha']['mukhi'], 'text' => 'Your Lagna lord is '.$V::PLANET_NAMES[$r['rudraksha']['lagna_lord']].'. Please consult before wearing.'];
                break;
            case 'numerology':
                $s[] = ['rows' => [['Mulank (root)', (string) $r['mulank']], ['Bhagyank (destiny)', (string) $r['bhagyank']], ['Name number', $r['name_number']['number'].' (total '.$r['name_number']['total'].')'],
                    ['Ruling planet', $V::PLANET_NAMES[Numerology::PLANET[$r['mulank']]]], ['Day', $r['traits']['day']], ['Colours', implode(', ', $r['traits']['colours'])]]];
                break;
            case 'name-number':
                $s[] = ['highlight' => (string) $r['name_number']['number'], 'text' => 'Total '.$r['name_number']['total'].': '.implode(' ', array_map(fn ($l) => $l[0].'='.$l[1], $r['name_number']['letters']))];
                break;
            case 'destiny-number':
                $s[] = ['highlight' => (string) $r['bhagyank'], 'rows' => [['Bhagyank (destiny number)', (string) $r['bhagyank']], ['Mulank', (string) $r['mulank']]]];
                break;
            case 'lo-shu-grid':
                $g = $r['loshu'];
                $s[] = ['title' => 'Lo Shu grid', 'table' => ['head' => [], 'rows' => array_map(fn ($row) => array_map(fn ($d) => $g['counts'][$d] ? str_repeat((string) $d, $g['counts'][$d]) : '—', $row), $g['layout'])],
                    'rows' => [['Missing numbers', $g['missing'] ? implode(', ', $g['missing']) : 'None'], ['Complete planes', $g['complete_planes'] ? implode(', ', $g['complete_planes']) : 'None']]];
                break;
            case 'personal-year':
                $s[] = ['highlight' => (string) $r['personal_year'], 'text' => 'Personal year '.$r['year'].'.'];
                break;
            case 'favourable-alphabets':
                $s[] = ['rows' => [['Mulank', (string) $r['mulank']], ['Friendly numbers', implode(', ', $r['traits']['friendly'])], ['Favourable letters (Chaldean)', implode(' ', $r['letters'])]]];
                break;
            case 'lucky-colour':
                $s[] = ['highlight' => implode(', ', $r['traits']['colours']), 'text' => 'Colours associated with Mulank '.$r['mulank'].'.'];
                break;
            case 'unlucky-colour':
                $s[] = ['highlight' => implode(', ', $r['traits']['avoid_colours']), 'text' => 'Colours traditionally used less with Mulank '.$r['mulank'].'. Nothing harmful — simply less preferred.'];
                break;
            case 'lucky-dates':
            case 'unlucky-dates':
                $list = $r['tool'] === 'lucky-dates' ? $r['dates']['favourable'] : $r['dates']['less_favourable'];
                $s[] = ['title' => CarbonImmutable::parse($r['month'].'-01')->format('F Y').', Mulank '.$r['mulank'], 'highlight' => $list ? implode(', ', $list) : 'None',
                    'text' => 'Based only on the day number. For important events use a proper Muhurat.'];
                break;
            case 'vehicle-number':
                $s[] = ['highlight' => (string) $r['vehicle']['number'], 'text' => $r['plate'].' reduces to '.$r['vehicle']['number'].'. With Mulank '.$r['mulank'].' this is traditionally '.$r['vehicle']['verdict'].'.'];
                break;
            case 'love-calculator':
                $s[] = ['highlight' => ucfirst($r['love']['relation']), 'text' => 'Name numbers '.$r['love']['a'].' and '.$r['love']['b'].'. Just for fun — real relationships depend on people, not numbers.'];
                break;
            case 'flames':
                $s[] = ['highlight' => (string) $r['flames'], 'text' => 'Just a game.'];
                break;
        }
        $note = $c ? 'Method: '.$c['method']['ephemeris'].', '.$c['method']['ayanamsha'].' ayanamsha, '.$c['method']['houses'].'. Accurate birth time matters.' : null;

        return ['sections' => array_values(array_filter($s)), 'note' => $note];
    }
}
