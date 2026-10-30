<?php

namespace App\Domain\Showcase;

use App\Support\Settings\SettingsRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Acharya Ji's public profile, edited in Admin › About & awards: biography text, stats, home-page
 * hero text and portraits (settings profile.* / home.*, each with an optional Hindi version), and
 * the lists on the About and Awards pages (table profile_entries). Used by the website, the app
 * (GET /api/v1/profile), structured data and llms.txt.
 */
class Profile
{
    public const SECTIONS = [
        'award' => 'Awards and honours',
        'education' => 'Education',
        'position' => 'Positions',
        'expertise' => 'Areas of expertise',
        'research' => 'Research papers',
        'book' => 'Books',
        'media' => 'Media and columns',
    ];

    public const AWARD_GROUPS = ['international' => 'International', 'national' => 'National'];

    /** Text fields: key => [label, default (English, translated through lang/*.json), textarea rows]. */
    public const TEXT = [
        'profile.job_title' => ['Job title (one line, used by search engines)', 'Vedic astrologer; Director, Jyotirveda Vigyan Sansthan, Patna', 1],
        'profile.headline' => ['Line under the name on the About page', 'Ph.D. in Astrology · Director, Jyotirveda Vigyan Sansthan · Patna, Bihar', 1],
        'profile.summary' => ['Short summary (search engines, AI answers, the app)', 'Acharya Dr. Rajnath Jha is a Vedic astrologer from Patna, Bihar, with a Ph.D. in Astrology (K.S.D.S.U., Darbhanga, 2014) and more than 30 years of practice in Jyotish, horoscope analysis, Vastu Shastra, numerology and palmistry. He is Director of Jyotirveda Vigyan Sansthan, served 17 years as Jyotish and Karmkand consultant at Jyotish Mandap, Mahavir Mandir Trust, Patna, and has guided more than 1,00,000 clients.', 4],
        'profile.biography' => ['Biography (leave an empty line between paragraphs)', "Acharya Dr. Rajnath Jha is an astrologer from Bihar and Director of Jyotirveda Vigyan Sansthan. His work covers Vedic astrology, horoscope analysis, palmistry, Vastu Shastra and numerology, and he has guided more than 1,00,000 clients over 30+ years.\n\nFor 17 years he served as the Jyotish and Karmkand consultant at Jyotish Mandap, Mahavir Mandir Trust, Patna. He became widely known through the \"Kaal Dhamal\" programme on Radio Mirchi 98.3 FM, and has been associated with Hindustan, Dainik Jagran and Dainik Bhaskar, and with channels including Maurya TV, ETV Bihar-Jharkhand, News18, Kashish News and Darsh TV.\n\nHe has lectured at Banaras Hindu University and K.S.D.S.U. Darbhanga, and presented research on earthquakes, cancer, arthritis and asthma from the perspective of Jyotish. He is now laying the foundation of a Gurukul in Madhubani to preserve Vedic and Vedanga education.", 12],
        'profile.alumni' => ['University (alumni of)', 'Kameshwar Singh Darbhanga Sanskrit University', 1],
        'profile.languages' => ['Languages spoken (separate with commas)', 'Hindi, Maithili, Sanskrit, English', 1],
        'profile.stat_years' => ['Stat: years of practice', '30+', 1],
        'profile.stat_clients' => ['Stat: clients guided', '1,00,000+', 1],
        'profile.stat_awards' => ['Stat: awards (leave empty to count the awards listed below)', '', 1],
        'home.eyebrow' => ['Home page: small line above the heading', 'Vedic astrology · Vastu · Remedies', 1],
        'home.subtitle' => ['Home page: text under the heading', 'Ph.D. in Astrology and Director of Jyotirveda Vigyan Sansthan, Patna, with more than 30 years of practice in Jyotish, Vastu, numerology and palmistry.', 3],
    ];

    /** Portraits: key => [label, default file under public/]. */
    public const PHOTOS = [
        'profile.photo_about' => ['About page portrait', 'images/owner/portrait-red-480.jpg'],
        'profile.photo_home' => ['Home page portrait', 'images/owner/portrait-navy-960.jpg'],
    ];

    private const CACHE_KEY = 'showcase.profile.v1';

    public function __construct(private SettingsRepository $settings) {}

    /** Picks the Hindi (or other locale) column when it is filled in, otherwise the English one. */
    public static function tr(object $row, string $field): ?string
    {
        $loc = app()->getLocale();
        $local = $loc !== 'en' && property_exists($row, $field.'_'.$loc) ? $row->{$field.'_'.$loc} : null;

        return filled($local) ? $local : $row->{$field};
    }

    public function text(string $key): string
    {
        $loc = app()->getLocale();
        if ($loc !== 'en' && filled($v = $this->settings->get($key.'.'.$loc))) {
            return (string) $v;
        }
        $v = $this->settings->get($key);

        return filled($v) ? (string) $v : (self::TEXT[$key][1] === '' ? '' : __(self::TEXT[$key][1]));
    }

    /** English text, for structured data and llms.txt. */
    public function english(string $key): string
    {
        $v = $this->settings->get($key);

        return filled($v) ? (string) $v : self::TEXT[$key][1];
    }

    /** @return list<string> */
    public function paragraphs(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/', $this->text('profile.biography')))));
    }

    /** @return list<string> */
    public function languages(bool $english = false): array
    {
        $raw = $english ? $this->english('profile.languages') : $this->text('profile.languages');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function photo(string $key): string
    {
        $v = $this->settings->get($key);

        return filled($v) && Gallery::fileExists((string) $v) ? (string) $v : self::PHOTOS[$key][1];
    }

    public function name(): string
    {
        return (string) ($this->settings->get('brand.practitioner_name') ?: config('brand')['brand.practitioner_name']);
    }

    /** All published entries, grouped by section. Cached; cleared on every change. */
    public function all(): Collection
    {
        // plain arrays in the cache: the cache store does not unserialize objects
        $rows = Cache::remember(self::CACHE_KEY, 600, fn () => DB::table('profile_entries')->where('published', true)
            ->orderBy('sort')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all());

        return collect($rows)->map(fn ($r) => (object) $r)->groupBy('section');
    }

    public function entries(string $section): Collection
    {
        return $this->all()->get($section, collect());
    }

    /** Clears the profile cache and the crawl files that list profile and gallery content. */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('seo:sitemap:'.url('/'));
        Cache::forget('seo:llms-full:'.url('/'));
    }

    /** One-line text of an entry, e.g. "Hind Gaurav Award — Indore, 2024". */
    public static function line(object $e, bool $english = false): string
    {
        $title = $english ? $e->title : self::tr($e, 'title');
        $detail = $english ? $e->detail : self::tr($e, 'detail');

        return trim($title.($detail ? ' — '.$detail : '').($e->year && ! str_contains((string) $title.$detail, (string) $e->year) ? ' ('.$e->year.')' : ''));
    }

    /** @return array{years: string, clients: string, awards: string, languages: string} */
    public function stats(): array
    {
        $awards = $this->text('profile.stat_awards');

        return ['years' => $this->text('profile.stat_years'), 'clients' => $this->text('profile.stat_clients'),
            'awards' => $awards !== '' ? $awards : (string) $this->entries('award')->count(), 'languages' => (string) count($this->languages())];
    }

    /** "Recognition" on the home page: entries marked "highlight" (awards, positions, education), or the first four awards. */
    public function homeHighlights(): Collection
    {
        $featured = collect(['award', 'position', 'education'])->flatMap(fn ($s) => $this->entries($s)->where('featured', true))->values();

        return ($featured->isNotEmpty() ? $featured : $this->entries('award'))->take(5)->values();
    }

    /** For the app (GET /api/v1/profile). */
    public function toApi(): array
    {
        $url = fn (?string $p) => $p ? asset($p) : null;
        $sections = [];
        foreach (self::SECTIONS as $key => $label) {
            $sections[] = ['key' => $key, 'label' => __($label), 'entries' => $this->entries($key)->map(fn ($e) => [
                'title' => self::tr($e, 'title'), 'detail' => self::tr($e, 'detail'), 'year' => $e->year, 'group' => $e->group,
                'group_label' => $e->group ? __(self::AWARD_GROUPS[$e->group] ?? $e->group) : null, 'image_url' => $url($e->image_path),
                'link_url' => $e->link_url, 'featured' => (bool) $e->featured, 'line' => self::line($e)])->values()];
        }

        return ['name' => $this->name(), 'job' => $this->text('profile.job_title'), 'headline' => $this->text('profile.headline'),
            'summary' => $this->text('profile.summary'), 'biography' => $this->paragraphs(), 'languages' => $this->languages(), 'stats' => $this->stats(),
            'home' => ['eyebrow' => $this->text('home.eyebrow'), 'subtitle' => $this->text('home.subtitle')],
            'photos' => ['about' => $url($this->photo('profile.photo_about')), 'home' => $url($this->photo('profile.photo_home'))],
            'sections' => $sections, 'web' => ['about' => url('/about'), 'awards' => url('/awards'), 'gallery' => url('/gallery')]];
    }
}
