<?php

namespace App\Http\Controllers\Web;

use App\Domain\Astro\Gochar;
use App\Domain\Astro\Muhurat;
use App\Domain\Astro\Tools;
use App\Domain\Showcase\Gallery;
use App\Domain\Showcase\Profile;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Models\Offering;
use App\Support\Business;
use App\Support\Money;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Crawl files: sitemap.xml (with hreflang alternates), robots.txt, and llms.txt / llms-full.txt
 * (plain-text summaries for AI answer engines — "GEO"). Cached for an hour.
 */
class SeoController extends Controller
{
    /** @return list<array{0: string, 1: ?string, 2: string, 3: string}> [path, lastmod, changefreq, priority] */
    private function urls(): array
    {
        $today = now()->toDateString();
        $u = [['/', $today, 'daily', '1.0'], ['/services', $today, 'daily', '0.9'], ['/about', '2026-10-28', 'monthly', '0.8'], ['/contact', null, 'monthly', '0.8'],
            ['/faq', null, 'monthly', '0.6'], ['/rashifal', $today, 'daily', '0.8'], ['/panchang', $today, 'daily', '0.8'], ['/calendar', $today, 'weekly', '0.6'],
            ['/festivals', null, 'monthly', '0.7'], ['/muhurat', null, 'monthly', '0.7'], ['/gochar', $today, 'daily', '0.7'], ['/navagraha', null, 'monthly', '0.5'],
            ['/tools', null, 'monthly', '0.8'], ['/gems', $today, 'weekly', '0.6'],
            ['/awards', null, 'monthly', '0.7'], ['/gallery', $today, 'weekly', '0.7'], ['/privacy', null, 'yearly', '0.2'], ['/terms', null, 'yearly', '0.2']];
        foreach (['nakshatra', 'chinese', 'numerology', 'tarot'] as $k) {
            $u[] = ["/rashifal/$k", $today, 'weekly', '0.5'];
        }
        foreach (Offering::public()->orderBy('sort')->get(['slug', 'updated_at']) as $o) {
            $u[] = ['/services/'.$o->slug, $o->updated_at?->toDateString(), 'weekly', '0.8'];
        }
        foreach (DB::table('products')->where('active', true)->get(['slug', 'updated_at']) as $p) {
            $u[] = ['/gems/'.$p->slug, substr((string) $p->updated_at, 0, 10) ?: null, 'weekly', '0.5'];
        }
        foreach (DB::table('gallery_albums')->where('published', true)->get(['slug', 'updated_at']) as $a) {
            $u[] = ['/gallery/'.$a->slug, substr((string) $a->updated_at, 0, 10) ?: null, 'monthly', '0.6'];
        }
        foreach (array_keys(Tools::TOOLS) as $t) {
            $u[] = ['/tools/'.$t, null, 'monthly', '0.6'];
        }
        foreach (ContentController::signSlugs() as $s) {
            $u[] = ['/rashi/'.$s, $today, 'daily', '0.6'];
        }
        foreach (Gochar::PLANETS as $p) {
            $u[] = ['/gochar/'.$p, null, 'weekly', '0.6'];
        }
        foreach (array_keys(Muhurat::EVENTS) as $e) {
            $u[] = ['/muhurat/'.$e, null, 'monthly', '0.6'];
        }
        foreach ([now()->year, now()->year + 1] as $y) {
            $u[] = ['/festivals?year='.$y, null, 'monthly', '0.5'];
        }

        return $u;
    }

    public function sitemap(): Response
    {
        $xml = Cache::remember('seo:sitemap:'.url('/'), 3600, function () {
            $langs = SetLocale::enabled();
            $loc = fn (string $path, string $l) => url($path).($l === 'en' ? '' : (str_contains($path, '?') ? '&' : '?').'lang='.$l);
            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
            foreach ($this->urls() as [$path, $mod, $freq, $prio]) {
                foreach ($langs as $l) {
                    $out .= '<url><loc>'.e($loc($path, $l)).'</loc>';
                    if (count($langs) > 1) {
                        foreach ($langs as $alt) {
                            $out .= '<xhtml:link rel="alternate" hreflang="'.$alt.'" href="'.e($loc($path, $alt)).'"/>';
                        }
                        $out .= '<xhtml:link rel="alternate" hreflang="x-default" href="'.e($loc($path, 'en')).'"/>';
                    }
                    $out .= ($mod ? '<lastmod>'.$mod.'</lastmod>' : '').'<changefreq>'.$freq.'</changefreq><priority>'.($l === 'en' ? $prio : number_format((float) $prio * 0.9, 1)).'</priority></url>'."\n";
                }
            }

            return $out.'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function robots(): Response
    {
        $txt = "User-agent: *\nAllow: /\n"
            ."Disallow: /admin\nDisallow: /staff\nDisallow: /account\nDisallow: /checkout\nDisallow: /call\nDisallow: /files\nDisallow: /palm-photos\nDisallow: /puja-photos\n"
            ."Disallow: /places/search\nDisallow: /enquiries\nDisallow: /assistant/chat\nDisallow: /design-system\nDisallow: /api/\nDisallow: /webhooks/\n"
            ."Disallow: /*?*q=\n\n"
            // AI crawlers are welcome to read public pages (answer engines cite them)
            ."User-agent: GPTBot\nAllow: /\nDisallow: /account\nDisallow: /admin\n\n"
            ."User-agent: Google-Extended\nAllow: /\n\n"
            ."User-agent: PerplexityBot\nAllow: /\n\n"
            ."User-agent: ClaudeBot\nAllow: /\n\n"
            .'Sitemap: '.url('/sitemap.xml')."\n";

        return response($txt, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    /** Short llms.txt (https://llmstxt.org): who, what, where, and the key pages. */
    public function llms(Business $b): Response
    {
        $services = Offering::public()->with(['translations', 'variants'])->orderBy('sort')->get()
            ->map(fn ($o) => '- ['.$o->t('en')?->title.']('.url('/services/'.$o->slug).'): '.$o->t('en')?->summary.(($p = $o->fromPriceMinor()) ? ' From '.Money::format($p).'.' : ''))->implode("\n");
        $p = app(Profile::class);
        $st = $p->stats();
        $txt = '# '.$b->name()."\n\n> ".$p->english('profile.summary')."\n\n"
            .'Consultations are given in person at the office in Kankarbagh, Patna (Bihar, India) and online by video or audio call, in '.implode(', ', $b->languages()).".\n\n"
            ."## Key facts\n"
            .'- Astrologer: '.$p->name().' — '.$p->english('profile.job_title').'; '.$st['years'].' years; '.$st['clients'].' clients; '.$st['awards'].' awards'."\n"
            .'- Address: '.$b->addressLine()."\n"
            .'- Phone / WhatsApp: '.implode(', ', array_filter([$b->phone(), $b->phone2()]))."\n"
            .'- Email: '.$b->email()."\n"
            .'- Hours (IST): '.implode('; ', array_map(fn ($r) => $r[0].' '.$r[1], $b->hoursRows()))."\n"
            ."- Payments: online by UPI, cards and net banking (Razorpay); prices in INR\n\n"
            ."## Services\n".$services."\n\n"
            ."## Free tools\n"
            .'- [Free calculators]('.url('/tools').'): 26 calculators including Kundli, Kundli matching (Ashtakoota), Moon sign, Nakshatra, Sade Sati, numerology and baby names'."\n"
            .'- [Daily Panchang]('.url('/panchang').'): tithi, nakshatra, yoga, karana, Rahu Kaal, Choghadiya and Hora for any city'."\n"
            .'- [Muhurat finder]('.url('/muhurat').'), [Festival calendar]('.url('/festivals').'), [Vrat calendar]('.url('/calendar').'), [Grah Gochar]('.url('/gochar').'), [Rashifal]('.url('/rashifal').")\n\n"
            ."## About\n- [Biography, education and awards](".url('/about').")\n- [Awards and honours](".url('/awards').")\n- [Photo and video gallery: pujas, events, awards](".url('/gallery').")\n- [Contact and timings](".url('/contact').")\n- [Questions and answers](".url('/faq').")\n\n"
            ."## Optional\n- [Full details for AI systems](".url('/llms-full.txt').")\n";

        return response($txt, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    /** llms-full.txt: services with prices, FAQs, biography and awards, as plain text. */
    public function llmsFull(Business $b): Response
    {
        $txt = Cache::remember('seo:llms-full:'.url('/'), 3600, function () use ($b) {
            $p = app(Profile::class);
            $out = '# '.$b->name()." — full information\n\n".$p->english('profile.summary')."\n";
            foreach (Profile::SECTIONS as $key => $label) {
                $list = $p->entries($key);
                if ($list->isNotEmpty()) {
                    $out .= "\n## ".$label."\n- ".$list->map(fn ($e) => Profile::line($e, true))->implode("\n- ")."\n";
                }
            }
            $albums = app(Gallery::class)->publicAlbums();
            if ($albums->isNotEmpty()) {
                $out .= "\n## Gallery\n- ".$albums->map(fn ($a) => $a->title.($a->event_date ? ' ('.$a->event_date.')' : '').': '.route('gallery.show', $a->slug))->implode("\n- ")."\n";
            }
            $out .= "\n## Services and prices (INR)\n";
            foreach (Offering::public()->with(['translations', 'variants', 'family'])->orderBy('family_id')->orderBy('sort')->get() as $o) {
                $t = $o->t('en');
                $out .= "\n### ".$t?->title.' ('.$o->family->name.")\nURL: ".url('/services/'.$o->slug)."\n".$t?->summary."\n";
                if ($t?->includes) {
                    $out .= 'Includes: '.implode('; ', $t->includes)."\n";
                }
                foreach ($o->variants->where('active', true) as $v) {
                    $out .= '- '.$v->name.': '.($v->price_minor ? Money::format((int) $v->price_minor) : 'price on request')."\n";
                }
            }
            $out .= "\n## Questions and answers\n";
            foreach (DB::table('faqs')->where('published', true)->where('locale', 'en')->orderBy('sort')->get() as $f) {
                $out .= "\nQ: ".$f->question."\nA: ".strip_tags($f->answer)."\n";
            }
            $out .= "\n## Contact\n".$b->addressLine()."\nPhone: ".implode(', ', array_filter([$b->phone(), $b->phone2()]))."\nEmail: ".$b->email()."\nHours (IST): ".implode('; ', array_map(fn ($r) => $r[0].' '.$r[1], $b->hoursRows())).".\n"
                ."\nNote: astrological guidance is traditional and interpretive and does not replace medical, legal or financial advice. Childbirth timing is never suggested.\n";

            return $out;
        });

        return response($txt, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
