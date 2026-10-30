<?php

namespace App\Http\Controllers\Web;

use App\Domain\Showcase\Gallery;
use App\Domain\Showcase\Profile;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Public About, Awards and Gallery pages (content from Admin › About & awards and Admin › Gallery). */
class ShowcaseController extends Controller
{
    public function __construct(private Profile $profile, private Gallery $gallery) {}

    /**
     * Photos for the awards blocks: award entries with a photo, then photos from published
     * "Awards and honours" albums.
     *
     * @return Collection<int, array{src: string, alt: string}>
     */
    public static function awardPhotos(int $limit): Collection
    {
        $fromEntries = app(Profile::class)->entries('award')->whereNotNull('image_path')->map(fn ($e) => ['src' => $e->image_path, 'alt' => Profile::line($e)]);
        $fromAlbums = DB::table('gallery_items as i')->join('gallery_albums as a', 'a.id', '=', 'i.album_id')
            ->where('a.published', true)->where('a.category', 'awards')->where('i.kind', 'photo')
            ->orderBy('a.sort')->orderBy('i.sort')->limit($limit)->get(['i.path', 'i.caption', 'i.caption_hi', 'a.title'])
            ->map(fn ($i) => ['src' => $i->path, 'alt' => Profile::tr($i, 'caption') ?: $i->title]);

        return $fromEntries->concat($fromAlbums)->filter(fn ($p) => Gallery::fileExists($p['src']))->unique('src')->take($limit)->values();
    }

    public function about(): View
    {
        return view('public.about', ['p' => $this->profile, 'albums' => $this->gallery->publicAlbums(null, 3, true), 'awardPhotos' => self::awardPhotos(2)]);
    }

    public function awards(): View
    {
        return view('public.awards', ['p' => $this->profile, 'albums' => $this->gallery->publicAlbums('awards')]);
    }

    public function gallery(Request $request): View
    {
        $category = $request->query('category');
        $category = is_string($category) && isset(Gallery::CATEGORIES[$category]) ? $category : null;
        $all = $this->gallery->publicAlbums();

        return view('public.gallery.index', ['albums' => $category ? $all->where('category', $category)->values() : $all, 'category' => $category,
            'categories' => collect(Gallery::CATEGORIES)->only($all->pluck('category')->unique()->all())]);
    }

    public function album(string $slug): View
    {
        $album = $this->gallery->publishedAlbum($slug);
        abort_unless($album, 404);

        return view('public.gallery.show', ['album' => $album, 'items' => $this->gallery->items($album->id),
            'more' => $this->gallery->publicAlbums($album->category, 4)->where('id', '!=', $album->id)->take(3)->values()]);
    }
}
