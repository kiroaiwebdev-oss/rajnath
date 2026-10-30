<?php

namespace App\Domain\Showcase;

use App\Support\Uploads\SafeImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Public photo and video gallery (pujas and anushthans, awards, events, media, office). Photos
 * are uploaded by staff, re-encoded by SafeImage (EXIF/GPS removed) into a full image and a
 * thumbnail with WebP siblings on the public "uploads" disk. Videos are YouTube links, shown with
 * youtube-nocookie.com after a click. Albums are drafts until someone with content.publish
 * publishes them.
 */
class Gallery
{
    public const CATEGORIES = [
        'puja' => 'Pujas and anushthans',
        'awards' => 'Awards and honours',
        'events' => 'Events and seminars',
        'media' => 'Media and press',
        'office' => 'Office and visitors',
        'other' => 'Other',
    ];

    public const DISK = 'uploads';

    public const MAX_ITEMS = 300;

    public const MAX_UPLOAD = 10;

    /** YouTube video id from the usual URL forms (watch, youtu.be, shorts, embed, live). */
    public static function youtubeId(string $url): ?string
    {
        $url = trim($url);
        if (! preg_match('#^https://(www\.|m\.)?(youtube\.com|youtu\.be|youtube-nocookie\.com)/#i', $url)) {
            return null;
        }
        $patterns = ['#youtu\.be/([A-Za-z0-9_-]{11})#', '#[?&]v=([A-Za-z0-9_-]{11})#', '#/(?:shorts|embed|live)/([A-Za-z0-9_-]{11})#'];
        foreach ($patterns as $p) {
            if (preg_match($p, $url, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    public static function videoThumb(string $id): string
    {
        return 'https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg';
    }

    public static function videoEmbed(string $id): string
    {
        return 'https://www.youtube-nocookie.com/embed/'.$id.'?autoplay=1&rel=0';
    }

    public static function videoWatch(string $id): string
    {
        return 'https://www.youtube.com/watch?v='.$id;
    }

    /** @param array{title: string, category: string, slug?: ?string, title_hi?: ?string, description?: ?string, description_hi?: ?string, event_date?: ?string, location?: ?string, sort?: ?int, featured?: bool, published?: bool} $d */
    public function createAlbum(array $d, bool $canPublish): object
    {
        $id = DB::table('gallery_albums')->insertGetId($this->row($d, $canPublish, null) + ['ref' => (string) Str::ulid(), 'created_at' => now()]);

        return DB::table('gallery_albums')->find($id);
    }

    public function updateAlbum(object $album, array $d, bool $canPublish): object
    {
        DB::table('gallery_albums')->where('id', $album->id)->update($this->row($d, $canPublish, $album));

        return DB::table('gallery_albums')->find($album->id);
    }

    private function row(array $d, bool $canPublish, ?object $album): array
    {
        $slug = Str::slug(($d['slug'] ?? null) ?: $d['title']) ?: 'album';
        $base = $slug;
        for ($n = 2; DB::table('gallery_albums')->where('slug', $slug)->when($album, fn ($q) => $q->where('id', '!=', $album->id))->exists(); $n++) {
            $slug = $base.'-'.$n;
        }
        // Only publishers can change what the public sees; authors' edits keep the current state.
        $published = $canPublish ? (bool) ($d['published'] ?? false) : (bool) ($album->published ?? false);
        $featured = $canPublish ? (bool) ($d['featured'] ?? false) : (bool) ($album->featured ?? false);

        return ['slug' => $slug, 'title' => $d['title'], 'title_hi' => $d['title_hi'] ?? null, 'description' => $d['description'] ?? null,
            'description_hi' => $d['description_hi'] ?? null, 'category' => $d['category'], 'event_date' => $d['event_date'] ?? null,
            'location' => $d['location'] ?? null, 'sort' => (int) ($d['sort'] ?? 0), 'published' => $published, 'featured' => $featured,
            'published_at' => $published ? ($album->published_at ?? now()) : null, 'updated_at' => now()];
    }

    /** @param list<UploadedFile> $files */
    public function addPhotos(object $album, array $files): int
    {
        $count = DB::table('gallery_items')->where('album_id', $album->id)->count();
        if ($count + count($files) > self::MAX_ITEMS) {
            throw ValidationException::withMessages(['photos' => 'An album can hold up to '.self::MAX_ITEMS.' items. Start a new album.']);
        }
        $sort = (int) DB::table('gallery_items')->where('album_id', $album->id)->max('sort');
        $stored = [];
        try {
            foreach (array_values($files) as $i => $file) {
                $img = SafeImage::storeForWeb($file, self::DISK, 'gallery/'.$album->ref, 'photos');
                $stored[] = $img;
                DB::table('gallery_items')->insert(['album_id' => $album->id, 'kind' => 'photo', 'path' => 'uploads/'.$img['path'], 'thumb_path' => 'uploads/'.$img['thumb'],
                    'width' => $img['width'], 'height' => $img['height'], 'sort' => $sort + $i + 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        } catch (ValidationException $e) {
            // a bad file stops the batch; the photos already saved stay, the message names the problem
            throw ValidationException::withMessages(['photos' => (count($stored) ? count($stored).' photo(s) saved. ' : '').'Photo '.(count($stored) + 1).': '.collect($e->errors())->flatten()->first()]);
        }
        DB::table('gallery_albums')->where('id', $album->id)->update(['updated_at' => now()]);

        return count($stored);
    }

    public function addVideo(object $album, string $url, ?string $caption): void
    {
        $id = self::youtubeId($url);
        if (! $id) {
            throw ValidationException::withMessages(['video_url' => 'Paste a YouTube link, for example https://www.youtube.com/watch?v=…']);
        }
        DB::table('gallery_items')->insert(['album_id' => $album->id, 'kind' => 'video', 'video_id' => $id, 'caption' => $caption,
            'sort' => (int) DB::table('gallery_items')->where('album_id', $album->id)->max('sort') + 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function deleteItem(object $item): void
    {
        DB::table('gallery_items')->where('id', $item->id)->delete();
        DB::table('gallery_albums')->where('cover_item_id', $item->id)->update(['cover_item_id' => null]);
        self::deleteFile($item->path);
    }

    public function deleteAlbum(object $album): void
    {
        $items = DB::table('gallery_items')->where('album_id', $album->id)->get();
        DB::table('gallery_albums')->where('id', $album->id)->delete();
        foreach ($items as $i) {
            self::deleteFile($i->path);
        }
    }

    /** Whether a public image exists (uploaded files live on the uploads disk, bundled ones under public/). */
    public static function fileExists(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        return str_starts_with($path, 'uploads/') ? Storage::disk(self::DISK)->exists(substr($path, strlen('uploads/'))) : is_file(public_path($path));
    }

    /** Only uploaded files are deleted; the bundled photos under images/ stay. */
    public static function deleteFile(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/')) {
            SafeImage::deleteForWeb(self::DISK, substr($path, strlen('uploads/')));
        }
    }

    public function items(int $albumId): Collection
    {
        return DB::table('gallery_items')->where('album_id', $albumId)->orderBy('sort')->orderBy('id')->get();
    }

    /** Published albums with cover and counts, newest event first. */
    public function publicAlbums(?string $category = null, ?int $limit = null, bool $featuredOnly = false): Collection
    {
        $albums = DB::table('gallery_albums')->where('published', true)->when($category, fn ($q) => $q->where('category', $category))
            ->when($featuredOnly, fn ($q) => $q->where('featured', true))
            ->orderBy('sort')->orderByDesc('event_date')->orderByDesc('id')->when($limit, fn ($q) => $q->limit($limit))->get();

        return $this->withCovers($albums);
    }

    public function withCovers(Collection $albums): Collection
    {
        if ($albums->isEmpty()) {
            return $albums;
        }
        $items = DB::table('gallery_items')->whereIn('album_id', $albums->pluck('id'))->orderBy('sort')->orderBy('id')->get()->groupBy('album_id');

        return $albums->map(function ($a) use ($items) {
            $list = $items->get($a->id, collect());
            $a->photo_count = $list->where('kind', 'photo')->count();
            $a->video_count = $list->where('kind', 'video')->count();
            $a->cover = ($a->cover_item_id ? $list->firstWhere('id', $a->cover_item_id) : null) ?? $list->firstWhere('kind', 'photo') ?? $list->first();

            return $a;
        });
    }

    public function publishedAlbum(string $slug): ?object
    {
        return DB::table('gallery_albums')->where('slug', $slug)->where('published', true)->first();
    }

    /** Cover image URL of an album (photo thumbnail or the YouTube still). */
    public static function coverUrl(object $album): ?string
    {
        $c = $album->cover ?? null;

        return ! $c ? null : ($c->kind === 'photo' ? asset($c->thumb_path ?: $c->path) : self::videoThumb($c->video_id));
    }

    /** API shape of one item. */
    public static function itemToApi(object $i): array
    {
        return $i->kind === 'photo'
            ? ['kind' => 'photo', 'url' => asset($i->path), 'thumb_url' => asset($i->thumb_path ?: $i->path), 'width' => $i->width, 'height' => $i->height, 'caption' => Profile::tr($i, 'caption')]
            : ['kind' => 'video', 'video_id' => $i->video_id, 'url' => self::videoWatch($i->video_id), 'thumb_url' => self::videoThumb($i->video_id), 'caption' => Profile::tr($i, 'caption')];
    }

    public static function albumToApi(object $a): array
    {
        return ['slug' => $a->slug, 'title' => Profile::tr($a, 'title'), 'description' => Profile::tr($a, 'description'), 'category' => $a->category,
            'category_label' => __(self::CATEGORIES[$a->category] ?? $a->category), 'event_date' => $a->event_date, 'location' => $a->location,
            'cover_url' => self::coverUrl($a), 'photo_count' => $a->photo_count ?? null, 'video_count' => $a->video_count ?? null, 'web_url' => route('gallery.show', $a->slug)];
    }
}
