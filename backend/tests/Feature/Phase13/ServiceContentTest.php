<?php

namespace Tests\Feature\Phase13;

use App\Models\Offering;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\MarketPriceSeeder;
use Database\Seeders\ServiceContentSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Full text and an own illustration for every catalogue service; owner edits are kept. */
class ServiceContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_placeholder_text_is_replaced_and_every_service_has_its_own_image(): void
    {
        $this->seed(CatalogueSeeder::class);
        $this->seed(MarketPriceSeeder::class);
        // the owner changed one summary and one photo before the content seed ran
        $career = Offering::where('slug', 'career-report')->first();
        $career->translations()->where('locale', 'en')->update(['summary' => 'Owner summary']);
        DB::table('offerings')->where('slug', 'numerology')->update(['image_path' => 'uploads/services/mine.jpg']);

        $this->seed(ServiceContentSeeder::class);

        $this->assertSame(0, DB::table('offering_translations')->where('summary', 'like', '%details to be finalised%')->count());
        $this->assertSame('Owner summary', $career->t('en')->summary);
        $this->assertSame('uploads/services/mine.jpg', Offering::where('slug', 'numerology')->value('image_path'));
        $this->assertSame('images/owner/portrait-navy-480.jpg', Offering::where('slug', 'consultation-with-acharya-ji')->value('image_path'));
        $art = DB::table('offerings')->where('image_path', 'like', 'images/art/%')->pluck('image_path');
        $this->assertGreaterThan(80, $art->count());
        $this->assertSame($art->count(), $art->unique()->count());
        foreach ($art as $p) {
            $this->assertFileExists(public_path($p));
        }

        $this->get('/services/sade-sati-report')->assertOk()->assertSee('images/art/sade-sati-report', false)->assertDontSee('details to be finalised');
        $this->get('/services/sade-sati-report?lang=hi')->assertOk()->assertSee('साढ़े');
        $this->get('/courses')->assertOk()->assertSee('Coming soon');
    }

    public function test_store_starter_list_is_hidden_until_the_owner_adds_real_stock(): void
    {
        $this->seed(StoreSeeder::class);
        $this->assertSame(count(StoreSeeder::ITEMS), DB::table('products')->where('active', false)->where('stock', 0)->count());
        $this->assertSame(0, DB::table('products')->whereNull('image_path')->count());
        $this->get('/gems')->assertOk()->assertDontSee('Yellow Sapphire (Pukhraj)');
    }
}
