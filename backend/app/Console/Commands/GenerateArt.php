<?php

namespace App\Console\Commands;

use App\Support\Art\ServiceArt;
use Database\Seeders\BlogSeeder;
use Database\Seeders\ServiceContentSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Developer tool: renders the service illustrations (ServiceArt) to public/images/art as JPG and
 * WebP with headless Chromium. The files are committed, so production never runs this.
 */
class GenerateArt extends Command
{
    protected $signature = 'arj:generate-art {--only= : one slug} {--force : redraw existing files}';

    protected $description = 'Render the service illustrations to public/images/art (JPG + WebP).';

    /** Illustrations for pages that are not services: key => [motif, palette]. */
    public const EXTRA = [
        'family-consultation' => ['zodiac', 'consultation'], 'family-vastu' => ['house', 'vastu'], 'family-remedies' => ['gem', 'remedy'],
        'family-puja' => ['diya', 'puja'], 'family-ask-a-question' => ['scroll', 'written_question'], 'family-reports' => ['chart', 'report'],
        'family-bundles' => ['star', 'bundle'], 'family-chadhava' => ['bell', 'chadhava'],
        'courses' => ['book', 'other'], 'blog' => ['scroll', 'other'], 'panchang' => ['sun', 'report'], 'rashifal' => ['zodiac', 'consultation'],
        'gochar' => ['saturn', 'report'], 'muhurat' => ['kalash', 'puja'], 'festivals' => ['diya', 'chadhava'], 'kundli' => ['chart', 'consultation'],
        'kundli-matching' => ['rings', 'bundle'], 'moon-sign' => ['moon', 'written_question'], 'gems' => ['gem', 'remedy'], 'pooja-samagri' => ['kalash', 'puja'],
        'calculators' => ['numbers', 'written_question'], 'navagraha' => ['navgrah', 'report'],
    ];

    public function handle(): int
    {
        $bin = env('PDF_CHROME_BINARY', '/usr/bin/chromium');
        if (! is_executable($bin)) {
            $this->error("Chromium not found at $bin (set PDF_CHROME_BINARY).");

            return self::FAILURE;
        }
        $families = DB::table('offerings as o')->join('service_families as f', 'f.id', '=', 'o.family_id')->pluck('f.code', 'o.slug');
        $jobs = [];
        foreach (ServiceContentSeeder::motifs() as $slug => $motif) {
            $jobs[$slug] = [$motif, $families[$slug] ?? 'other'];
        }
        $jobs += self::EXTRA;
        $palette = ['kundli' => 'consultation', 'grah-gochar' => 'report', 'vivah' => 'chadhava', 'vastu' => 'vastu', 'numerology' => 'written_question',
            'vrat-tyohar' => 'puja', 'upay' => 'remedy', 'hastrekha' => 'bundle'];
        foreach (StoreSeeder::ITEMS as [$category, $name, , , , $colour]) {
            $motif = match ($category) {
                'gemstone' => 'gem:'.$colour, 'rudraksha', 'mala' => 'rudraksha', 'yantra' => 'yantra', 'kit' => 'kalash', default => 'diya'
            };
            $jobs['store-'.Str::slug($name)] = [$motif, 'remedy'];
        }
        foreach (BlogSeeder::posts() as $post) {
            $jobs['blog-'.$post['slug']] = [$post['motif'], $palette[$post['category']] ?? 'other'];
        }
        if ($only = $this->option('only')) {
            $jobs = array_intersect_key($jobs, [$only => 1]);
        }
        $dir = public_path('images/art');
        @mkdir($dir, 0775, true);
        $tmp = sys_get_temp_dir().'/arj-art-'.getmypid();
        @mkdir($tmp);
        $made = 0;
        foreach ($jobs as $key => [$motif, $family]) {
            if (! $this->option('force') && is_file("$dir/$key.jpg")) {
                continue;
            }
            $html = "$tmp/$key.html";
            file_put_contents($html, '<!doctype html><meta charset="utf-8"><style>html,body{margin:0;background:#000}svg{display:block}</style>'.ServiceArt::svg($key, $motif, $family));
            $png = "$tmp/$key.png";
            $p = new Process([$bin, '--headless', '--no-sandbox', '--disable-gpu', '--hide-scrollbars', '--force-device-scale-factor=1',
                '--window-size='.ServiceArt::W.','.ServiceArt::H, '--screenshot='.$png, 'file://'.$html]);
            $p->setTimeout(60)->run();
            $img = is_file($png) ? @imagecreatefrompng($png) : false;
            if (! $img) {
                $this->warn("$key: render failed ".$p->getErrorOutput());

                continue;
            }
            imagejpeg($img, "$dir/$key.jpg", 84);
            imagewebp($img, "$dir/$key.webp", 80);
            $made++;
        }
        array_map('unlink', glob("$tmp/*") ?: []);
        @rmdir($tmp);
        $this->info("Rendered $made illustrations into public/images/art.");

        return self::SUCCESS;
    }
}
