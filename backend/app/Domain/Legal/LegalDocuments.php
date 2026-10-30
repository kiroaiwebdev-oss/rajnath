<?php

namespace App\Domain\Legal;

use App\Domain\Identity\CustomerAuth;
use App\Support\Audit\AuditLogger;
use App\Support\Business;
use App\Support\Settings\SettingsRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\Table\TableExtension;

/**
 * Legal pages (privacy, terms, refunds, shipping) as versioned Markdown, edited in Admin › Legal
 * pages. Drafts are edited freely; publishing freezes the text as a new version and supersedes the
 * previous one. Placeholders such as {email} are filled from Admin › Settings when shown.
 */
class LegalDocuments
{
    /** kind => [title, public path, route name] */
    public const KINDS = [
        'privacy' => ['Privacy policy', '/privacy', 'privacy'],
        'terms' => ['Terms of use', '/terms', 'terms'],
        'refunds' => ['Cancellation and refund policy', '/refund-policy', 'legal.refunds'],
        'shipping' => ['Shipping and delivery policy', '/shipping-policy', 'legal.shipping'],
    ];

    public const LOCALES = ['en' => 'English', 'hi' => 'Hindi'];

    public const PLACEHOLDERS = ['{brand}', '{practitioner}', '{email}', '{phone}', '{address}', '{source_url}', '{account_url}', '{privacy_url}', '{terms_url}', '{refunds_url}', '{shipping_url}'];

    private const CACHE = 'legal.current.v1';

    public function __construct(private AuditLogger $audit) {}

    /** Published version for a kind, in the locale if there is one, else English. */
    public function current(string $kind, ?string $locale = null): ?object
    {
        $locale ??= app()->getLocale();
        $all = Cache::remember(self::CACHE, 600, fn () => DB::table('legal_documents')->where('status', 'published')->get()->map(fn ($r) => (array) $r)->all());
        $rows = collect($all)->map(fn ($r) => (object) $r)->where('kind', $kind);

        return $rows->firstWhere('locale', $locale) ?? $rows->firstWhere('locale', 'en');
    }

    public function isPublished(string $kind): bool
    {
        return $this->current($kind, 'en') !== null;
    }

    /** The version a customer agrees to at sign-up and checkout: the newest published privacy/terms date. */
    public static function consentVersion(): string
    {
        $v = rescue(fn () => DB::table('legal_documents')->whereIn('kind', ['privacy', 'terms'])->where('locale', 'en')->where('status', 'published')->max('version'), null, false);

        return $v ?: CustomerAuth::TERMS_VERSION;
    }

    /** @return Collection<int, object> published and superseded versions, newest first */
    public function history(string $kind, string $locale = 'en'): Collection
    {
        return DB::table('legal_documents')->where('kind', $kind)->where('locale', $locale)->whereIn('status', ['published', 'superseded'])
            ->orderByDesc('published_at')->orderByDesc('id')->get();
    }

    public function version(string $kind, string $version, string $locale = 'en'): ?object
    {
        return DB::table('legal_documents')->where('kind', $kind)->where('locale', $locale)->where('version', $version)->whereIn('status', ['published', 'superseded'])->first();
    }

    /** Markdown → safe HTML. Raw HTML is stripped and javascript: links are removed. */
    public function render(string $markdown): HtmlString
    {
        $b = app(Business::class);
        $s = app(SettingsRepository::class);
        $values = [$b->name(), $b->practitioner(), (string) $b->email(), (string) $b->phone(), $b->addressLine(),
            (string) ($s->get('source.repository_url') ?: config('brand')['source.repository_url']), route('account.dashboard'),
            url('/privacy'), url('/terms'), url('/refund-policy'), url('/shipping-policy')];
        // placeholders are replaced before rendering and escaped for Markdown, so settings cannot inject markup
        $md = str_replace(self::PLACEHOLDERS, array_map(fn ($v) => addcslashes($v, '\\`*_{}[]()#+!<>|'), $values), $markdown);

        return new HtmlString(Str::markdown($md, ['html_input' => 'strip', 'allow_unsafe_links' => false, 'max_nesting_level' => 20], [new AutolinkExtension, new TableExtension]));
    }

    /** Next free version label for today: 2026-11-02, then 2026-11-02.2, … */
    private function nextVersion(string $kind, string $locale): string
    {
        $base = now('Asia/Kolkata')->toDateString();
        $taken = DB::table('legal_documents')->where('kind', $kind)->where('locale', $locale)->where('version', 'like', $base.'%')->pluck('version')->all();
        for ($n = 1; ; $n++) {
            $v = $n === 1 ? $base : $base.'.'.$n;
            if (! in_array($v, $taken, true)) {
                return $v;
            }
        }
    }

    /** The open draft for a kind and locale (created from the published text when missing). */
    public function draft(string $kind, string $locale, int $userId): object
    {
        $d = DB::table('legal_documents')->where('kind', $kind)->where('locale', $locale)->where('status', 'draft')->first();
        if ($d) {
            return $d;
        }
        $from = DB::table('legal_documents')->where('kind', $kind)->where('locale', $locale)->where('status', 'published')->first()
            ?? DB::table('legal_documents')->where('kind', $kind)->where('locale', 'en')->where('status', 'published')->first();
        $id = DB::table('legal_documents')->insertGetId(['kind' => $kind, 'locale' => $locale, 'version' => 'draft-'.Str::lower(Str::random(8)),
            'title' => $from->title ?? __(self::KINDS[$kind][0]), 'body' => $from->body ?? '', 'status' => 'draft', 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('legal_documents')->find($id);
    }

    public function saveDraft(object $draft, string $title, string $body, ?string $note): void
    {
        DB::table('legal_documents')->where('id', $draft->id)->where('status', 'draft')->update(['title' => $title, 'body' => $body, 'change_note' => $note, 'updated_at' => now()]);
    }

    public function publish(object $draft, int $userId, ?string $effectiveOn = null): object
    {
        if (trim($draft->body) === '') {
            throw ValidationException::withMessages(['body' => 'The page is empty.']);
        }
        $current = DB::table('legal_documents')->where('kind', $draft->kind)->where('locale', $draft->locale)->where('status', 'published')->first();
        if ($current && $current->body === $draft->body && $current->title === $draft->title) {
            throw ValidationException::withMessages(['body' => 'Nothing has changed since the published version.']);
        }
        $version = $this->nextVersion($draft->kind, $draft->locale);
        DB::transaction(function () use ($draft, $userId, $effectiveOn, $version) {
            DB::table('legal_documents')->where('kind', $draft->kind)->where('locale', $draft->locale)->where('status', 'published')->update(['status' => 'superseded', 'updated_at' => now()]);
            DB::table('legal_documents')->where('id', $draft->id)->update(['status' => 'published', 'version' => $version, 'effective_on' => $effectiveOn ?: now('Asia/Kolkata')->toDateString(),
                'published_by' => $userId, 'published_at' => now(), 'updated_at' => now()]);
        });
        self::forget();
        $this->audit->record('legal.published', null, ['kind' => $draft->kind, 'locale' => $draft->locale, 'version' => $version, 'note' => $draft->change_note]);

        return DB::table('legal_documents')->find($draft->id);
    }

    public function discard(object $draft): void
    {
        DB::table('legal_documents')->where('id', $draft->id)->where('status', 'draft')->delete();
    }

    /** Takes a published page down (e.g. a shipping policy that no longer applies). The text stays in the history. */
    public function unpublish(string $kind, string $locale): void
    {
        DB::table('legal_documents')->where('kind', $kind)->where('locale', $locale)->where('status', 'published')->update(['status' => 'superseded', 'updated_at' => now()]);
        self::forget();
        $this->audit->record('legal.unpublished', null, ['kind' => $kind, 'locale' => $locale]);
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE);
        Cache::forget('seo:sitemap:'.url('/'));
    }
}
