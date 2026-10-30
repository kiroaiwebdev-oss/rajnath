<?php

namespace Tests\Feature\Phase13;

use App\Domain\Showcase\Gallery;
use App\Domain\Showcase\Profile;
use App\Models\Offering;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\ShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\StaffHelpers;
use Tests\TestCase;

/** Admin-managed About profile, awards, gallery, opening hours and service photos. */
class ShowcaseTest extends TestCase
{
    use RefreshDatabase, StaffHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->seed(ShowcaseSeeder::class);
        $this->syncRbac();
        Storage::fake(Gallery::DISK);
    }

    public function test_seeded_profile_feeds_about_awards_home_api_and_structured_data(): void
    {
        $this->get('/about')->assertOk()->assertSee('Mahavir Mandir')->assertSee('Varahmihiracharyakrit')->assertSee('Jyotishprabha')->assertSee('At the office in Kankarbagh');
        $this->get('/awards')->assertOk()->assertSee('Professor Award 2026')->assertSee('International')->assertSee('Jyotish Shri Award')->assertSee('"CollectionPage"', false);
        $this->get('/')->assertOk()->assertSee('Honoured in India and abroad')->assertSee('Pujas, events and moments');
        $ld = $this->get('/')->getContent();
        $this->assertStringContainsString('Hind Gaurav Award', $ld);
        $this->getJson('/api/v1/profile')->assertOk()->assertJsonPath('data.stats.awards', '14')->assertJsonPath('data.sections.0.key', 'award')
            ->assertJsonPath('data.sections.0.entries.1.image_url', asset('images/owner/award-malaysia-2025-480.jpg'));
        $this->getJson('/api/v1/site')->assertOk()->assertJsonPath('data.about.name', 'Acharya Dr. Rajnath Jha');
        $this->get('/llms-full.txt')->assertOk()->assertSee('## Awards and honours')->assertSee('## Gallery');
        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/awards'))->assertSee(url('/gallery/awards-and-honours'));

        // a second seed run never overwrites the owner's content
        DB::table('profile_entries')->where('title', 'Hind Gaurav Award')->update(['title' => 'Hind Gaurav Samman']);
        $this->seed(ShowcaseSeeder::class);
        $this->assertSame(1, DB::table('profile_entries')->where('title', 'Hind Gaurav Samman')->count());
        $this->assertSame(2, DB::table('gallery_albums')->count());
    }

    public function test_publisher_edits_text_stats_and_awards_with_photo(): void
    {
        $author = $this->makeStaff(['content_author']);
        $this->asStaff($author)->get(route('admin.profile.edit'))->assertOk()->assertSee('Text, stats');
        $this->asStaff($author)->put(route('admin.profile.text'), ['profile__stat_years' => '35+'])->assertForbidden();
        $this->asStaff($this->makeStaff(['support']))->get(route('admin.profile.edit'))->assertForbidden();

        $pub = $this->makeStaff(['content_publisher']);
        $this->asStaff($pub)->put(route('admin.profile.text'), [
            'profile__stat_years' => '35+', 'home__eyebrow' => 'Jyotish · Vastu · Karmkand', 'home__eyebrow__hi' => 'ज्योतिष · वास्तु · कर्मकांड',
            'profile__biography' => "First paragraph.\n\nSecond paragraph.",
            'profile__photo_about' => UploadedFile::fake()->image('me.jpg', 900, 1200),
        ])->assertRedirect(route('admin.profile.edit'));
        $this->get('/')->assertSee('35+')->assertSee('Jyotish · Vastu · Karmkand');
        $this->get('/?lang=hi')->assertSee('ज्योतिष · वास्तु · कर्मकांड');
        $about = $this->get('/about?lang=en')->assertSee('<p>Second paragraph.</p>', false)->getContent();
        $photo = json_decode(DB::table('settings')->where('key', 'profile.photo_about')->value('value'));
        $this->assertStringStartsWith('uploads/profile/', $photo);
        $this->assertStringContainsString($photo, $about);
        Storage::disk(Gallery::DISK)->assertExists(substr($photo, 8));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);

        $this->asStaff($pub)->post(route('admin.profile.entry'), ['section' => 'award', 'title' => 'Vedic Ratna Award', 'detail' => 'Varanasi', 'year' => '2026',
            'group' => 'national', 'published' => '1', 'featured' => '1', 'image' => UploadedFile::fake()->image('award.jpg', 800, 600)])->assertSessionHasNoErrors();
        $e = DB::table('profile_entries')->where('title', 'Vedic Ratna Award')->first();
        $this->assertStringStartsWith('uploads/profile/', $e->image_path);
        Storage::disk(Gallery::DISK)->assertExists(substr($e->image_path, 8));
        $this->get('/awards')->assertSee('Vedic Ratna Award')->assertSee('Varanasi');
        $this->get('/')->assertSee('Vedic Ratna Award');
        $this->getJson('/api/v1/profile')->assertJsonPath('data.stats.awards', '15');
        $this->assertStringContainsString('Vedic Ratna Award — Varanasi', $this->get('/about')->getContent());

        // hiding and deleting
        $this->asStaff($pub)->post(route('admin.profile.entry'), ['id' => $e->id, 'section' => 'award', 'title' => 'Vedic Ratna Award'])->assertSessionHasNoErrors();
        $this->get('/awards')->assertDontSee('Vedic Ratna Award');
        $this->asStaff($pub)->delete(route('admin.profile.entry.destroy', $e->id))->assertRedirect();
        Storage::disk(Gallery::DISK)->assertMissing(substr($e->image_path, 8));
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.profile_entry_deleted']);

        // bad input
        $this->asStaff($pub)->post(route('admin.profile.entry'), ['section' => 'nope', 'title' => 'x'])->assertSessionHasErrors('section');
        $this->asStaff($pub)->post(route('admin.profile.entry'), ['section' => 'award', 'title' => 'x', 'image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('image');
    }

    public function test_gallery_albums_photos_videos_and_publishing(): void
    {
        $author = $this->makeStaff(['content_author']);
        $this->asStaff($author)->post(route('admin.gallery.store'), ['title' => 'Maha Rudrabhishek, Shravan 2026', 'category' => 'puja', 'event_date' => '2026-08-03',
            'location' => 'Patna', 'published' => '1'])->assertRedirect();
        $a = DB::table('gallery_albums')->where('slug', 'maha-rudrabhishek-shravan-2026')->first();
        $this->assertFalse((bool) $a->published, 'authors cannot publish');

        $this->asStaff($author)->post(route('admin.gallery.upload', $a->ref), ['photos' => [UploadedFile::fake()->image('a.jpg', 1600, 1200), UploadedFile::fake()->image('b.png', 800, 800)]])
            ->assertSessionHasNoErrors();
        $items = DB::table('gallery_items')->where('album_id', $a->id)->get();
        $this->assertCount(2, $items);
        foreach ($items as $i) {
            Storage::disk(Gallery::DISK)->assertExists(substr($i->path, 8));
            Storage::disk(Gallery::DISK)->assertExists(substr($i->thumb_path, 8));
        }
        $this->assertSame(1600, (int) $items[0]->width);
        $this->asStaff($author)->post(route('admin.gallery.upload', $a->ref), ['photos' => [UploadedFile::fake()->image('tiny.jpg', 100, 100)]])->assertSessionHasErrors('photos');
        $this->asStaff($author)->post(route('admin.gallery.video', $a->ref), ['video_url' => 'https://evil.example/watch?v=dQw4w9WgXcQ'])->assertSessionHasErrors('video_url');
        $this->asStaff($author)->post(route('admin.gallery.video', $a->ref), ['video_url' => 'https://youtu.be/dQw4w9WgXcQ?si=x', 'caption' => 'Aarti'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('gallery_items', ['album_id' => $a->id, 'kind' => 'video', 'video_id' => 'dQw4w9WgXcQ']);
        $this->asStaff($author)->get(route('admin.gallery.index'))->assertOk()->assertSee('Maha Rudrabhishek')->assertSee('Draft');
        $this->asStaff($author)->get(route('admin.gallery.edit', $a->ref))->assertOk()->assertSee('i.ytimg.com/vi/dQw4w9WgXcQ', false)->assertDontSee('Delete album');
        $this->asStaff($this->makeStaff(['support']))->get(route('admin.gallery.index'))->assertForbidden();

        // drafts are not public
        $this->get('/gallery/'.$a->slug)->assertNotFound();
        $this->getJson('/api/v1/gallery/'.$a->slug)->assertNotFound();
        $this->asStaff($author)->delete(route('admin.gallery.destroy', $a->ref))->assertForbidden();

        $pub = $this->makeStaff(['content_publisher']);
        $this->asStaff($pub)->put(route('admin.gallery.update', $a->ref), ['title' => 'Maha Rudrabhishek, Shravan 2026', 'category' => 'puja', 'event_date' => '2026-08-03',
            'location' => 'Patna', 'published' => '1', 'featured' => '1', 'description' => 'Eleven priests, one day.'])->assertSessionHasNoErrors();
        $this->asStaff($pub)->put(route('admin.gallery.item', [$a->ref, $items[1]->id]), ['caption' => 'Purnahuti', 'cover' => '1'])->assertSessionHasNoErrors();

        $page = $this->get('/gallery/'.$a->slug)->assertOk()->assertSee('Eleven priests, one day.')->assertSee('Purnahuti')->assertSee('data-video="dQw4w9WgXcQ"', false)->getContent();
        $this->assertStringContainsString('"ImageGallery"', $page);
        $this->assertStringContainsString('"VideoObject"', $page);
        $this->get('/gallery?category=puja')->assertOk()->assertSee('Maha Rudrabhishek');
        $this->get('/')->assertSee('Maha Rudrabhishek');
        $api = $this->getJson('/api/v1/gallery/'.$a->slug)->assertOk()->assertJsonCount(3, 'data.items')->assertJsonPath('data.items.2.kind', 'video')
            ->assertJsonPath('data.cover_url', asset($items[1]->thumb_path));
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $api->json('data.items.2.url'));
        $this->getJson('/api/v1/gallery?category=puja')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.photo_count', 2);
        $this->getJson('/api/v1/gallery?category=bad')->assertUnprocessable();

        // removing a photo deletes its files; deleting the album deletes the rest
        $this->asStaff($author)->delete(route('admin.gallery.item.destroy', [$a->ref, $items[0]->id]))->assertRedirect();
        Storage::disk(Gallery::DISK)->assertMissing(substr($items[0]->path, 8));
        $this->asStaff($pub)->delete(route('admin.gallery.destroy', $a->ref))->assertRedirect(route('admin.gallery.index'));
        Storage::disk(Gallery::DISK)->assertMissing(substr($items[1]->path, 8));
        $this->assertSame(0, DB::table('gallery_items')->where('album_id', $a->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'gallery.album_deleted']);

        // the bundled seed photos are never deleted from disk
        $seed = DB::table('gallery_albums')->where('slug', 'awards-and-honours')->first();
        $this->asStaff($pub)->delete(route('admin.gallery.destroy', $seed->ref))->assertRedirect();
        $this->assertFileExists(public_path('images/owner/award-malaysia-2025-480.jpg'));
    }

    public function test_profile_cache_works_with_a_persistent_store(): void
    {
        config(['cache.default' => 'file']);
        Profile::forget();
        $this->get('/about')->assertOk()->assertSee('Hind Gaurav Award');
        $this->get('/about')->assertOk()->assertSee('Hind Gaurav Award'); // second request reads the cached rows
        Profile::forget();
    }

    public function test_youtube_links(): void
    {
        foreach (['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=1', 'https://youtu.be/dQw4w9WgXcQ', 'https://m.youtube.com/shorts/dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ'] as $u) {
            $this->assertSame('dQw4w9WgXcQ', Gallery::youtubeId($u), $u);
        }
        foreach (['http://youtu.be/dQw4w9WgXcQ', 'https://youtube.com.evil.io/watch?v=dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=short', 'javascript:alert(1)'] as $u) {
            $this->assertNull(Gallery::youtubeId($u), $u);
        }
    }

    public function test_opening_hours_and_address_are_editable(): void
    {
        $this->get('/contact')->assertSee('Thursday')->assertSee('Closed');
        $owner = $this->makeStaff(['owner']);
        $base = ['brand__name' => 'Astro Rajnath Jha', 'brand__practitioner_name' => 'Acharya Dr. Rajnath Jha', 'source__repository_url' => 'https://github.com/kiroaiwebdev-oss/rajnath',
            'contact__postal_code' => '800020', 'contact__locality' => 'Rajendra Nagar, Patna'];
        $hours = ['hours__mon_1' => '09:00-13:00', 'hours__tue_1' => '09:00-13:00', 'hours__wed_1' => '09:00 – 13:00', 'hours__thu_1' => '09:00-13:00',
            'hours__fri_1' => '09:00-13:00', 'hours__sat_1' => '09:00-13:00', 'hours__sat_2' => '16:00-20:00'];
        $this->asStaff($owner)->put(route('admin.settings.update'), $base + $hours + ['hours__sun_1' => '13:00-09:00'])->assertSessionHasErrors('hours__sun_1');
        $this->asStaff($owner)->put(route('admin.settings.update'), $base + ['hours__mon_1' => '9am'])->assertSessionHasErrors('hours__mon_1');
        $this->asStaff($owner)->put(route('admin.settings.update'), $base + $hours)->assertSessionHasNoErrors();

        $this->get('/contact')->assertSee('Mon – Fri')->assertSee('9:00 AM – 1:00 PM')->assertSee('4:00 PM – 8:00 PM')->assertSee('Sunday')->assertSee('Rajendra Nagar, Patna');
        $this->get('/')->assertSee('Mon – Fri: 9:00 AM – 1:00 PM');
        $html = $this->get('/faq')->getContent();
        $this->assertStringContainsString('"opens":"16:00","closes":"20:00"', $html);
        $this->assertStringContainsString('"addressLocality":"Rajendra Nagar, Patna"', $html);
        $this->getJson('/api/v1/site')->assertJsonPath('data.hours.0.days', 'Mon – Fri')->assertJsonPath('data.hours.2.time', 'Closed');
    }

    public function test_service_photo_upload(): void
    {
        $o = Offering::where('slug', 'janma-kundali-reading')->first();
        $admin = $this->makeStaff(['administrator']);
        $t = $o->t('en');
        $this->asStaff($admin)->put(route('admin.catalogue.update', $o->ref), ['family_id' => $o->family_id, 'slug' => $o->slug, 'title' => $t->title, 'summary' => $t->summary,
            'image' => UploadedFile::fake()->image('kundli.jpg', 1200, 900)])->assertSessionHasNoErrors();
        $path = $o->fresh()->image_path;
        $this->assertStringStartsWith('uploads/services/', $path);
        Storage::disk(Gallery::DISK)->assertExists(substr($path, 8));
        Storage::disk(Gallery::DISK)->assertExists(substr(str_replace('.jpg', '.webp', $path), 8));
    }
}
