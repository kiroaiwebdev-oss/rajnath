<?php

namespace Database\Seeders;

use App\Models\Offering;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fills the catalogue's placeholder text (reports, bundles, puja, chadhava) from
 * database/seeders/data/service_content.php, adds the Hindi title and summary, and gives every
 * service its own illustration (public/images/art/{slug}.jpg, made by `arj:generate-art`).
 * Owner edits always win: text is replaced only while it is still the seeded placeholder, a Hindi
 * translation only when none exists, and an image only when none was chosen or it is still the
 * photo the first seed put there.
 */
class ServiceContentSeeder extends Seeder
{
    private const PLACEHOLDER = '— details to be finalised before publication.';

    /** Illustration motifs for services whose text was written in CatalogueSeeder. */
    public const MOTIFS = [
        'janma-kundali-reading' => 'chart', 'love-marriage-compatibility' => 'rings', 'business-astrology' => 'briefcase', 'numerology' => 'numbers',
        'palmistry' => 'hand', 'vastu-consultation' => 'house', 'gemstone-guidance' => 'gem', 'vastu-pyramid' => 'pyramid', 'personalized-mantra' => 'om',
        'puja-rituals' => 'kalash', 'baby-muhurat-consultation' => 'baby', 'palmistry-photo-report' => 'hand', 'ask-one-question' => 'scroll',
        'team-astrologer-consultation' => 'zodiac', 'premium-astrologer-consultation' => 'star', 'elite-astrologer-consultation' => 'crown',
    ];

    /** Photos the first catalogue seed used (replaced by the illustration unless the owner changed them). */
    private const SEEDED_PHOTOS = ['images/owner/portrait-navy-480.jpg', 'images/owner/portrait-red-480.jpg', 'images/owner/writing-navy-480.jpg', 'images/owner/writing-red-480.jpg',
        'images/owner/seated-navy-480.jpg', 'images/owner/desk-black-480.jpg', 'images/owner/office-wide-960.jpg', 'images/owner/writing-red-desk-480.jpg', 'images/owner/awards-wall-wide-960.jpg'];

    /** The principal consultation keeps Acharya Ji's photo. */
    private const KEEP_PHOTO = ['consultation-with-acharya-ji'];

    /** @return array<string, array<string, mixed>> */
    public static function content(): array
    {
        return require database_path('seeders/data/service_content.php');
    }

    /** @return array<string, string> slug => motif for every service with an illustration */
    public static function motifs(): array
    {
        return self::MOTIFS + array_map(fn ($c) => $c['motif'], self::content());
    }

    public function run(): void
    {
        $content = self::content();
        foreach ($content as $slug => $c) {
            $o = Offering::where('slug', $slug)->first();
            $en = $o?->translations()->where('locale', 'en')->first();
            if (! $en) {
                continue;
            }
            if (str_ends_with((string) $en->summary, self::PLACEHOLDER)) {
                $en->fill(['title' => $c['title'], 'summary' => $c['summary'], 'body' => $c['body'], 'includes' => $c['includes'], 'audience' => $c['audience'],
                    'disclaimer' => $c['disclaimer'], 'meta_description' => $c['summary']])->save();
            }
            if (! $o->translations()->where('locale', 'hi')->exists()) {
                $o->translations()->create(['locale' => 'hi', 'title' => $c['title_hi'], 'summary' => $c['summary_hi'], 'meta_description' => $c['summary_hi']]);
            }
        }
        foreach (array_keys(self::motifs()) as $slug) {
            $file = 'images/art/'.$slug.'.jpg';
            if (in_array($slug, self::KEEP_PHOTO, true) || ! is_file(public_path($file))) {
                continue;
            }
            DB::table('offerings')->where('slug', $slug)
                ->where(fn ($q) => $q->whereNull('image_path')->orWhereIn('image_path', self::SEEDED_PHOTOS))
                ->update(['image_path' => $file, 'updated_at' => now()]);
        }
    }
}
