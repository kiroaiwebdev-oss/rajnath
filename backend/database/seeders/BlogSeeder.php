<?php

namespace Database\Seeders;

use App\Domain\Blog\Blog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Starting articles (database/seeders/data/blog_posts.php) as DRAFTS: Acharya Ji's team reads them
 * in Admin › Articles and publishes. Inserts only slugs that do not exist yet.
 */
class BlogSeeder extends Seeder
{
    /** @return list<array<string, mixed>> */
    public static function posts(): array
    {
        return require database_path('seeders/data/blog_posts.php');
    }

    public function run(): void
    {
        foreach (self::posts() as $p) {
            if (DB::table('posts')->where('slug', $p['slug'])->exists()) {
                continue;
            }
            $cover = 'images/art/blog-'.$p['slug'].'.jpg';
            DB::table('posts')->insert(['ref' => (string) Str::ulid(), 'slug' => $p['slug'], 'category' => $p['category'], 'title' => $p['title'], 'title_hi' => $p['title_hi'],
                'excerpt' => $p['excerpt'], 'excerpt_hi' => $p['excerpt_hi'], 'body' => trim($p['body']), 'tags' => json_encode($p['tags']), 'services' => json_encode($p['services']),
                'cover_path' => is_file(public_path($cover)) ? $cover : null, 'status' => 'draft', 'reading_minutes' => Blog::minutes($p['body']),
                'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
