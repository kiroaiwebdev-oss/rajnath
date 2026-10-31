<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Blog\Blog;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Articles for the app; the body comes as simple blocks (h2, h3, p, li, quote). */
class BlogApiController extends Controller
{
    public function index(Request $request, Blog $blog): JsonResponse
    {
        $d = $request->validate(['category' => ['nullable', Rule::in(array_keys(Blog::CATEGORIES))], 'q' => ['nullable', 'string', 'max:80']]);
        $page = $blog->published($d['category'] ?? null, $d['q'] ?? null, 20);

        return response()->json(['data' => collect($page->items())->map(fn ($p) => Blog::toApi($p))->values(),
            'meta' => ['categories' => collect(Blog::CATEGORIES)->map(fn ($l, $k) => ['key' => $k, 'label' => __($l)])->values(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage()]]);
    }

    public function show(string $slug, Blog $blog): JsonResponse
    {
        $p = $blog->find($slug);
        abort_unless($p, 404);

        return response()->json(['data' => Blog::toApi($p, true) + ['related' => $blog->related($p)->map(fn ($r) => Blog::toApi($r))->values()]]);
    }
}
