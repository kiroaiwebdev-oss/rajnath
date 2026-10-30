<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Showcase\Gallery;
use App\Domain\Showcase\Profile;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Profile, awards and gallery for the app (same content as /about, /awards and /gallery). */
class ShowcaseApiController extends Controller
{
    public function profile(Profile $profile): JsonResponse
    {
        return response()->json(['data' => $profile->toApi()]);
    }

    public function albums(Request $request, Gallery $gallery): JsonResponse
    {
        $d = $request->validate(['category' => ['nullable', Rule::in(array_keys(Gallery::CATEGORIES))]]);
        $all = $gallery->publicAlbums();
        $list = ($d['category'] ?? null) ? $all->where('category', $d['category'])->values() : $all;

        return response()->json(['data' => $list->map(fn ($a) => Gallery::albumToApi($a))->values(),
            'meta' => ['categories' => collect(Gallery::CATEGORIES)->only($all->pluck('category')->unique()->all())->map(fn ($l, $k) => ['key' => $k, 'label' => __($l)])->values()]]);
    }

    public function album(string $slug, Gallery $gallery): JsonResponse
    {
        $a = $gallery->publishedAlbum($slug);
        abort_unless($a, 404);
        $a = $gallery->withCovers(collect([$a]))->first();

        return response()->json(['data' => Gallery::albumToApi($a) + ['items' => $gallery->items($a->id)->map(fn ($i) => Gallery::itemToApi($i))->values()]]);
    }
}
