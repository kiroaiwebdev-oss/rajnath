<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\BirthProfiles;
use App\Http\Controllers\Controller;
use App\Models\BirthProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BirthProfileController extends Controller
{
    public function __construct(private BirthProfiles $profiles) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => BirthProfile::where('user_id', $request->user()->id)->orderByDesc('is_primary')->orderBy('name')->get()->map->toApi()]);
    }

    public function store(Request $request): JsonResponse
    {
        $p = $this->profiles->save($request->user(), $request->validate(BirthProfiles::rules()));

        return response()->json(['data' => $p->toApi(), 'notice' => BirthProfiles::resolutionNote($p->tz_resolution)], 201);
    }

    public function show(Request $request, string $ref): JsonResponse
    {
        return response()->json(['data' => $this->find($request, $ref)->toApi()]);
    }

    public function update(Request $request, string $ref): JsonResponse
    {
        $p = $this->profiles->save($request->user(), $request->validate(BirthProfiles::rules()), $this->find($request, $ref));

        return response()->json(['data' => $p->toApi(), 'notice' => BirthProfiles::resolutionNote($p->tz_resolution)]);
    }

    public function destroy(Request $request, string $ref): JsonResponse
    {
        $this->profiles->delete($this->find($request, $ref));

        return response()->json(null, 204);
    }

    private function find(Request $request, string $ref): BirthProfile
    {
        // Scoped to the caller: another user's ref gives 404, never their data.
        return BirthProfile::where('user_id', $request->user()->id)->where('ref', $ref)->firstOrFail();
    }
}
