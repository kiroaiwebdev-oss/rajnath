<?php

namespace App\Http\Controllers\Web;

use App\Domain\Blog\Blog;
use App\Http\Controllers\Controller;
use App\Models\Offering;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Public articles: /blog with category and search, and /blog/{slug}. */
class BlogController extends Controller
{
    public function __construct(private Blog $blog) {}

    public function index(Request $request): View
    {
        $category = is_string($c = $request->query('category')) && isset(Blog::CATEGORIES[$c]) ? $c : null;
        $q = is_string($s = $request->query('q')) ? mb_substr(trim($s), 0, 80) : '';

        return view('public.blog.index', ['posts' => $this->blog->published($category, $q ?: null), 'category' => $category, 'q' => $q]);
    }

    public function show(string $slug): View
    {
        $p = $this->blog->find($slug);
        abort_unless($p, 404);
        $body = Blog::body($p);

        return view('public.blog.show', ['p' => $p, 'html' => Blog::htmlWithAnchors($body), 'toc' => Blog::toc($body), 'faqs' => Blog::faqs($body),
            'related' => $this->blog->related($p), 'services' => Offering::public()->with(['translations', 'variants', 'family'])->whereIn('slug', json_decode($p->services ?? '[]', true) ?: [])->get()]);
    }
}
