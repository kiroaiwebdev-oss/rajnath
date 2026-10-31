<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Blog\Blog;
use App\Domain\Showcase\Profile;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLogger;
use App\Support\Uploads\SafeImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin › Articles: authors write drafts; content publishers publish, feature and delete. */
class BlogController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    private function post(string $ref): object
    {
        $p = DB::table('posts')->where('ref', $ref)->first();
        abort_unless($p, 404);

        return $p;
    }

    public function index(Request $request): View
    {
        $this->authorize('content.author');
        $status = in_array($request->query('status'), ['draft', 'published'], true) ? $request->query('status') : null;

        return view('admin.blog.index', ['posts' => DB::table('posts')->when($status, fn ($q) => $q->where('status', $status))->orderByDesc('updated_at')->paginate(40)->withQueryString(),
            'status' => $status, 'counts' => DB::table('posts')->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')]);
    }

    public function edit(?string $ref = null): View
    {
        $this->authorize('content.author');
        $p = $ref ? $this->post($ref) : null;

        return view('admin.blog.edit', ['p' => $p, 'preview' => $p ? Blog::html($p->body) : null]);
    }

    public function save(Request $request, ?string $ref = null): RedirectResponse
    {
        $this->authorize('content.author');
        $p = $ref ? $this->post($ref) : null;
        $d = $request->validate([
            'title' => ['required', 'string', 'max:200'], 'title_hi' => ['nullable', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'category' => ['required', Rule::in(array_keys(Blog::CATEGORIES))],
            'excerpt' => ['required', 'string', 'max:400'], 'excerpt_hi' => ['nullable', 'string', 'max:400'],
            'body' => ['required', 'string', 'max:100000'], 'body_hi' => ['nullable', 'string', 'max:100000'],
            'tags' => ['nullable', 'string', 'max:300'], 'services' => ['nullable', 'string', 'max:500'],
            'cover' => ['nullable', 'file', 'max:8192'],
        ]);
        $slug = ($d['slug'] ?? null) ?: Str::slug($d['title']);
        if (DB::table('posts')->where('slug', $slug)->when($p, fn ($q) => $q->where('id', '!=', $p->id))->exists()) {
            return back()->withInput()->withErrors(['slug' => 'Another article already uses this web address.']);
        }
        $list = fn (?string $s) => array_values(array_filter(array_map('trim', explode(',', (string) $s))));
        $row = ['slug' => $slug, 'category' => $d['category'], 'title' => $d['title'], 'title_hi' => $d['title_hi'] ?? null, 'excerpt' => $d['excerpt'],
            'excerpt_hi' => $d['excerpt_hi'] ?? null, 'body' => $d['body'], 'body_hi' => $d['body_hi'] ?? null, 'tags' => json_encode($list($d['tags'] ?? null)),
            'services' => json_encode($list($d['services'] ?? null)), 'reading_minutes' => Blog::minutes($d['body']), 'updated_at' => now()];
        if ($request->hasFile('cover')) {
            $row['cover_path'] = 'uploads/'.SafeImage::storeForWeb($request->file('cover'), 'uploads', 'blog', 'cover')['path'];
        }
        if ($p) {
            DB::table('posts')->where('id', $p->id)->update($row);
        } else {
            $row += ['ref' => (string) Str::ulid(), 'status' => 'draft', 'author_id' => $request->user()->id, 'created_at' => now()];
            DB::table('posts')->insert($row);
        }
        Profile::forget();
        $this->audit->record('blog.saved', null, ['post' => $slug]);

        return redirect()->route('admin.blog.edit', DB::table('posts')->where('slug', $slug)->value('ref'))->with('status', 'Saved.');
    }

    /** publish | unpublish | feature | unfeature | delete, for one article or a ticked list. */
    public function action(Request $request): RedirectResponse
    {
        $this->authorize('content.publish');
        $d = $request->validate(['action' => ['required', Rule::in(['publish', 'unpublish', 'feature', 'unfeature', 'delete'])], 'refs' => ['required', 'array', 'min:1'], 'refs.*' => ['string', 'size:26']]);
        $q = DB::table('posts')->whereIn('ref', $d['refs']);
        $n = (clone $q)->count();
        match ($d['action']) {
            'publish' => (clone $q)->where('status', 'draft')->update(['status' => 'published', 'published_at' => now(), 'published_by' => $request->user()->id, 'updated_at' => now()]),
            'unpublish' => (clone $q)->update(['status' => 'draft', 'featured' => false, 'updated_at' => now()]),
            'feature' => (clone $q)->where('status', 'published')->update(['featured' => true]),
            'unfeature' => (clone $q)->update(['featured' => false]),
            'delete' => (clone $q)->delete(),
        };
        Profile::forget();
        $this->audit->record('blog.'.$d['action'], null, ['posts' => $d['refs']]);

        return redirect()->route('admin.blog.index')->with('status', $n.' article(s): '.$d['action'].' done.');
    }
}
