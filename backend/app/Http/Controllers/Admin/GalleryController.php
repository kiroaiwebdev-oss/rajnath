<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Showcase\Gallery;
use App\Domain\Showcase\Profile;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin › Gallery. Staff with media.manage upload photos, add YouTube videos and edit albums;
 * only content.publish can publish, feature on the home page, or delete an album.
 */
class GalleryController extends Controller
{
    public function __construct(private Gallery $gallery, private AuditLogger $audit) {}

    private function album(string $ref): object
    {
        $a = DB::table('gallery_albums')->where('ref', $ref)->first();
        abort_unless($a, 404);

        return $a;
    }

    private function item(object $album, int $id): object
    {
        $i = DB::table('gallery_items')->where('album_id', $album->id)->where('id', $id)->first();
        abort_unless($i, 404);

        return $i;
    }

    private function albumRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'], 'title_hi' => ['nullable', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/'],
            'category' => ['required', Rule::in(array_keys(Gallery::CATEGORIES))],
            'description' => ['nullable', 'string', 'max:3000'], 'description_hi' => ['nullable', 'string', 'max:3000'],
            'event_date' => ['nullable', 'date', 'before_or_equal:'.now()->addYear()->toDateString()],
            'location' => ['nullable', 'string', 'max:160'], 'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'published' => ['nullable', 'boolean'], 'featured' => ['nullable', 'boolean'],
        ];
    }

    private function changed(): void
    {
        Profile::forget();
    }

    public function index(Request $request): View
    {
        $this->authorize('media.manage');
        $category = $request->query('category');
        $albums = DB::table('gallery_albums')->when(is_string($category) && isset(Gallery::CATEGORIES[$category]), fn ($q) => $q->where('category', $category))
            ->orderBy('sort')->orderByDesc('event_date')->orderByDesc('id')->get();

        return view('admin.gallery.index', ['albums' => $this->gallery->withCovers($albums), 'category' => $category]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('media.manage');
        $a = $this->gallery->createAlbum($request->validate($this->albumRules()), $request->user()->can('content.publish'));
        $this->audit->record('gallery.album_created', null, ['album' => $a->ref, 'title' => $a->title, 'published' => (bool) $a->published]);
        $this->changed();

        return redirect()->route('admin.gallery.edit', $a->ref)->with('status', 'Album created. Now add photos or videos.');
    }

    public function edit(string $ref): View
    {
        $this->authorize('media.manage');
        $a = $this->album($ref);

        return view('admin.gallery.edit', ['album' => $a, 'items' => $this->gallery->items($a->id)]);
    }

    public function update(Request $request, string $ref): RedirectResponse
    {
        $this->authorize('media.manage');
        $a = $this->gallery->updateAlbum($this->album($ref), $request->validate($this->albumRules()), $request->user()->can('content.publish'));
        $this->audit->record('gallery.album_saved', null, ['album' => $a->ref, 'title' => $a->title, 'published' => (bool) $a->published, 'featured' => (bool) $a->featured]);
        $this->changed();

        return back()->with('status', $a->published ? 'Album saved and live.' : 'Album saved as a draft (not public yet).');
    }

    public function destroy(string $ref): RedirectResponse
    {
        $this->authorize('content.publish');
        $a = $this->album($ref);
        $this->gallery->deleteAlbum($a);
        $this->audit->record('gallery.album_deleted', null, ['album' => $a->ref, 'title' => $a->title]);
        $this->changed();

        return redirect()->route('admin.gallery.index')->with('status', 'Album deleted with its photos.');
    }

    public function upload(Request $request, string $ref): RedirectResponse
    {
        $this->authorize('media.manage');
        $a = $this->album($ref);
        $request->validate(['photos' => ['required', 'array', 'min:1', 'max:'.Gallery::MAX_UPLOAD], 'photos.*' => ['file', 'max:8192']],
            ['photos.max' => 'Upload up to '.Gallery::MAX_UPLOAD.' photos at a time.', 'photos.*.max' => 'Each photo must be under 8 MB.']);
        $n = $this->gallery->addPhotos($a, $request->file('photos'));
        $this->audit->record('gallery.photos_added', null, ['album' => $a->ref, 'count' => $n]);
        $this->changed();

        return back()->with('status', $n.' photo(s) added.');
    }

    public function addVideo(Request $request, string $ref): RedirectResponse
    {
        $this->authorize('media.manage');
        $a = $this->album($ref);
        $d = $request->validate(['video_url' => ['required', 'string', 'max:300'], 'caption' => ['nullable', 'string', 'max:300']]);
        $this->gallery->addVideo($a, $d['video_url'], $d['caption'] ?? null);
        $this->audit->record('gallery.video_added', null, ['album' => $a->ref, 'video' => Gallery::youtubeId($d['video_url'])]);
        $this->changed();

        return back()->with('status', 'Video added.');
    }

    public function updateItem(Request $request, string $ref, int $id): RedirectResponse
    {
        $this->authorize('media.manage');
        $a = $this->album($ref);
        $i = $this->item($a, $id);
        $d = $request->validate(['caption' => ['nullable', 'string', 'max:300'], 'caption_hi' => ['nullable', 'string', 'max:300'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'], 'cover' => ['nullable', 'boolean']]);
        DB::table('gallery_items')->where('id', $i->id)->update(['caption' => $d['caption'] ?? null, 'caption_hi' => $d['caption_hi'] ?? null,
            'sort' => (int) ($d['sort'] ?? $i->sort), 'updated_at' => now()]);
        if ($d['cover'] ?? false) {
            DB::table('gallery_albums')->where('id', $a->id)->update(['cover_item_id' => $i->id, 'updated_at' => now()]);
        }
        $this->changed();

        return back()->with('status', 'Saved.');
    }

    public function destroyItem(string $ref, int $id): RedirectResponse
    {
        $this->authorize('media.manage');
        $a = $this->album($ref);
        $i = $this->item($a, $id);
        $this->gallery->deleteItem($i);
        $this->audit->record('gallery.item_deleted', null, ['album' => $a->ref, 'item' => $i->id, 'kind' => $i->kind]);
        $this->changed();

        return back()->with('status', 'Removed from the album.');
    }
}
