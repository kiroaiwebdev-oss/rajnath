<?php

namespace Tests\Feature\Phase13;

use App\Domain\Blog\Blog;
use Database\Seeders\BlogSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\MarketPriceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\StaffHelpers;
use Tests\TestCase;

/** Articles: seeded as drafts, written by authors, published by publishers, shown with SEO data and in the app. */
class BlogTest extends TestCase
{
    use RefreshDatabase, StaffHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->seed(MarketPriceSeeder::class);
        $this->seed(BlogSeeder::class);
        $this->syncRbac();
    }

    public function test_seeded_articles_are_drafts_until_published(): void
    {
        $this->assertSame(24, DB::table('posts')->where('status', 'draft')->count());
        $slug = DB::table('posts')->where('slug', 'like', '%sade-sati%')->value('slug');
        $this->get('/blog')->assertOk()->assertSee('Articles will appear here soon.');
        $this->get('/blog/'.$slug)->assertNotFound();

        $this->asStaff($this->makeStaff(['content_author']))->post(route('admin.blog.action'), ['action' => 'publish', 'refs' => DB::table('posts')->pluck('ref')->all()])->assertForbidden();
        $pub = $this->makeStaff(['content_publisher']);
        $this->asStaff($pub)->get(route('admin.blog.index'))->assertOk()->assertSee('Drafts');
        $this->asStaff($pub)->post(route('admin.blog.action'), ['action' => 'publish', 'refs' => DB::table('posts')->pluck('ref')->all()])->assertRedirect();
        $this->assertSame(24, DB::table('posts')->where('status', 'published')->count());

        $html = $this->get('/blog/'.$slug)->assertOk()->assertSee('"BlogPosting"', false)->assertSee('"FAQPage"', false)->assertSee('On this page')->getContent();
        $this->assertStringContainsString('<h2 id="', $html);
        $this->get('/blog')->assertOk()->assertSee('min read');
        $this->get('/blog?category=vastu')->assertOk()->assertSee('Vastu');
        $this->get('/blog?q=Sade')->assertOk()->assertSee('Sade Sati');
        $this->get('/blog/'.$slug.'?lang=hi')->assertOk();
        $this->get('/sitemap.xml')->assertSee(url('/blog/'.$slug));
        $this->getJson('/api/v1/blog')->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/v1/blog/'.$slug)->assertOk()->assertJsonPath('data.blocks.0.type', 'p')->assertJsonStructure(['data' => ['related']]);
    }

    public function test_author_writes_and_html_is_stripped(): void
    {
        $author = $this->makeStaff(['content_author']);
        $this->asStaff($author)->post(route('admin.blog.store'), ['title' => 'Shubh muhurat for Griha Pravesh', 'category' => 'vastu', 'excerpt' => 'How a date is chosen.',
            'body' => "## Why muhurat\n\nTradition <script>alert(1)</script> says so.\n\n## Frequently asked questions\n\n### Is Saturday good?\n\nUsually avoided."])->assertRedirect();
        $p = DB::table('posts')->where('slug', 'shubh-muhurat-for-griha-pravesh')->first();
        $this->assertSame('draft', $p->status);
        $this->assertSame([['Is Saturday good?', 'Usually avoided.']], Blog::faqs($p->body));
        $this->asStaff($this->makeStaff(['content_publisher']))->post(route('admin.blog.action'), ['action' => 'publish', 'refs' => [$p->ref]]);
        $this->get('/blog/'.$p->slug)->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('Why muhurat');
    }
}
