<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Payments\Checkout;
use App\Domain\Reviews\Reviews;
use App\Http\Controllers\Controller;
use App\Models\Offering;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Reviews for the app: the public list and the customer's own reviews (same rules as the website). */
class ReviewApiController extends Controller
{
    public function __construct(private Reviews $reviews) {}

    public function index(Request $request): JsonResponse
    {
        $d = $request->validate(['service' => ['nullable', 'string', 'max:120'], 'rating' => ['nullable', 'integer', 'between:1,5']]);
        $offering = ($d['service'] ?? null) ? Offering::public()->where('slug', $d['service'])->first() : null;
        $page = $this->reviews->published($offering?->id, $d['rating'] ?? null, 20);

        return response()->json(['data' => collect($page->items())->map(fn ($r) => Reviews::toApi($r))->values(),
            'meta' => ['summary' => $this->reviews->summary($offering?->id), 'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function mine(Request $request): JsonResponse
    {
        $u = $request->user();

        return response()->json(['data' => DB::table('reviews')->where('user_id', $u->id)->orderByDesc('id')->get()->map(fn ($r) => Reviews::toOwnerApi($r))->values(),
            'meta' => ['suggested_name' => Reviews::suggestedName($u), 'reviewable_orders' => $this->reviews->reviewableOrders($u)
                ->map(fn ($o) => ['ref' => $o->ref, 'label' => Checkout::label($o->kind, json_decode($o->snapshot, true) ?: []), 'paid_at' => $o->paid_at])->values()]]);
    }

    public function store(Request $request): JsonResponse
    {
        $d = $request->validate(Reviews::rules() + ['order_ref' => ['required', 'string', 'size:26'], 'consent' => ['accepted']],
            ['consent.accepted' => 'Please agree that we may publish your review.']);

        return response()->json(['data' => Reviews::toOwnerApi($this->reviews->submit($request->user(), $d['order_ref'], $d))], 201);
    }

    private function own(Request $request, string $ref): object
    {
        $r = DB::table('reviews')->where('ref', $ref)->where('user_id', $request->user()->id)->first();
        abort_unless($r, 404);

        return $r;
    }

    public function update(Request $request, string $ref): JsonResponse
    {
        $r = $this->own($request, $ref);
        $this->reviews->edit($request->user(), $r, $request->validate(Reviews::rules()));

        return response()->json(['data' => Reviews::toOwnerApi(DB::table('reviews')->find($r->id))]);
    }

    public function destroy(Request $request, string $ref): JsonResponse
    {
        $this->reviews->deleteOwn($request->user(), $this->own($request, $ref));

        return response()->json(['data' => ['status' => 'deleted']]);
    }
}
