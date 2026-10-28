<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Places\PlaceSearch;
use App\Http\Controllers\Controller;
use App\Models\Offering;
use App\Models\ServiceFamily;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogueController extends Controller
{
    public function families(): JsonResponse
    {
        return response()->json(['data' => ServiceFamily::whereHas('offerings', fn ($q) => $q->where('status', 'active'))->orderBy('sort')->get()
            ->map(fn ($f) => ['code' => $f->code, 'slug' => $f->slug, 'name' => $f->name, 'summary' => $f->summary])]);
    }

    public function offerings(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $items = Offering::public()->with(['translations', 'variants', 'family'])
            ->when($request->query('family'), fn ($w, $f) => $w->whereHas('family', fn ($x) => $x->where('slug', $f)->orWhere('code', $f)))
            ->when($q !== '', fn ($w) => $w->whereHas('translations', fn ($t) => $t->where('title', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->orderBy('sort')->get();

        return response()->json(['data' => $items->map->toApi()]);
    }

    public function offering(string $slug): JsonResponse
    {
        $o = Offering::public()->with(['translations', 'variants', 'family'])->where('slug', $slug)->first();

        return $o ? response()->json(['data' => $o->toApi(true)]) : AuthController::error('not_found', 'Service not found.', 404);
    }

    public function places(Request $request, PlaceSearch $search): JsonResponse
    {
        return response()->json(['data' => $search->search((string) $request->query('q', '')), 'attribution' => 'Place data © GeoNames (CC BY 4.0)']);
    }
}
