<?php

namespace App\Domain\Blog;

use App\Domain\Showcase\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\Table\TableExtension;

/**
 * Articles: Markdown with an optional Hindi version. Authors write drafts; a content publisher
 * publishes them. Raw HTML in Markdown is stripped, so an article cannot inject scripts.
 */
class Blog
{
    public const CATEGORIES = [
        'kundli' => 'Kundli', 'grah-gochar' => 'Grah gochar', 'vivah' => 'Marriage', 'vastu' => 'Vastu', 'numerology' => 'Numerology',
        'vrat-tyohar' => 'Vrat and festivals', 'upay' => 'Remedies', 'hastrekha' => 'Palmistry',
    ];

    public static function minutes(string $body): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags($body)) / 200));
    }

    public static function html(string $markdown): HtmlString
    {
        return new HtmlString(Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false, 'max_nesting_level' => 20], [new AutolinkExtension, new TableExtension]));
    }

    /** Body in the current language (Hindi when written), else English. */
    public static function body(object $p): string
    {
        return Profile::tr($p, 'body');
    }

    /** "## Heading" sections for the table of contents: [id, text]. */
    public static function toc(string $markdown): array
    {
        preg_match_all('/^## (.+)$/m', $markdown, $m);

        return array_map(fn ($h) => [Str::slug($h), trim($h)], $m[1]);
    }

    /** HTML with ids on h2 so the table of contents can link to them. */
    public static function htmlWithAnchors(string $markdown): HtmlString
    {
        $html = (string) self::html($markdown);

        return new HtmlString(preg_replace_callback('#<h2>(.*?)</h2>#', fn ($m) => '<h2 id="'.Str::slug(html_entity_decode(strip_tags($m[1]))).'">'.$m[1].'</h2>', $html));
    }

    /** Questions under the "Frequently asked questions" heading: [[question, answer]]. */
    public static function faqs(string $markdown): array
    {
        $parts = preg_split('/^## .*(questions|प्रश्न).*$/mi', $markdown);
        if (count($parts) < 2) {
            return [];
        }
        preg_match_all('/^### (.+?)\R+(.+?)(?=\R### |\R## |\z)/ms', end($parts), $m, PREG_SET_ORDER);

        return array_map(fn ($x) => [trim($x[1]), trim(strip_tags((string) self::html(trim($x[2]))))], $m);
    }

    /** Simple blocks for the app: [{type: h2|h3|p|li|quote, text}]. */
    public static function blocks(string $markdown): array
    {
        $out = [];
        foreach (preg_split('/\R/', $markdown) as $line) {
            $l = trim($line);
            if ($l === '') {
                continue;
            }
            $plain = fn ($s) => trim(preg_replace(['/\*\*(.+?)\*\*/', '/\*(.+?)\*/', '/\[(.+?)\]\((.+?)\)/', '/`(.+?)`/'], ['$1', '$1', '$1', '$1'], $s));
            if (str_starts_with($l, '### ')) {
                $out[] = ['type' => 'h3', 'text' => $plain(substr($l, 4))];
            } elseif (str_starts_with($l, '## ')) {
                $out[] = ['type' => 'h2', 'text' => $plain(substr($l, 3))];
            } elseif (preg_match('/^([-*]|\d+\.) (.+)/', $l, $m)) {
                $out[] = ['type' => 'li', 'text' => $plain($m[2])];
            } elseif (str_starts_with($l, '> ')) {
                $out[] = ['type' => 'quote', 'text' => $plain(substr($l, 2))];
            } elseif (($n = count($out)) && $out[$n - 1]['type'] === 'p' && ! str_ends_with($out[$n - 1]['text'], "\n")) {
                $out[$n - 1]['text'] .= ' '.$plain($l);
            } else {
                $out[] = ['type' => 'p', 'text' => $plain($l)];
            }
        }

        return $out;
    }

    public function published(?string $category = null, ?string $q = null, int $perPage = 12): LengthAwarePaginator
    {
        return DB::table('posts')->where('status', 'published')->where('published_at', '<=', now())
            ->when($category, fn ($w) => $w->where('category', $category))
            ->when($q, fn ($w) => $w->where(fn ($x) => $x->where('title', 'like', '%'.addcslashes($q, '%_').'%')->orWhere('excerpt', 'like', '%'.addcslashes($q, '%_').'%')))
            ->orderByDesc('featured')->orderByDesc('published_at')->paginate($perPage)->withQueryString();
    }

    public function latest(int $n = 3): Collection
    {
        return DB::table('posts')->where('status', 'published')->where('published_at', '<=', now())->orderByDesc('published_at')->limit($n)->get();
    }

    public function find(string $slug): ?object
    {
        return DB::table('posts')->where('slug', $slug)->where('status', 'published')->where('published_at', '<=', now())->first();
    }

    public function related(object $p, int $n = 3): Collection
    {
        return DB::table('posts')->where('status', 'published')->where('published_at', '<=', now())->where('id', '!=', $p->id)
            ->orderByRaw('category = ? desc', [$p->category])->orderByDesc('published_at')->limit($n)->get();
    }

    public static function cover(object $p): string
    {
        return $p->cover_path ?: 'images/art/family-reports.jpg';
    }

    public static function toApi(object $p, bool $full = false): array
    {
        $out = ['slug' => $p->slug, 'title' => Profile::tr($p, 'title'), 'excerpt' => Profile::tr($p, 'excerpt'), 'category' => $p->category,
            'category_label' => __(self::CATEGORIES[$p->category] ?? $p->category), 'cover_url' => asset(self::cover($p)), 'reading_minutes' => (int) $p->reading_minutes,
            'published_at' => substr((string) $p->published_at, 0, 10), 'web_url' => route('blog.show', $p->slug)];
        if ($full) {
            $out += ['blocks' => self::blocks(self::body($p)), 'tags' => json_decode($p->tags ?? '[]', true), 'services' => json_decode($p->services ?? '[]', true)];
        }

        return $out;
    }
}
