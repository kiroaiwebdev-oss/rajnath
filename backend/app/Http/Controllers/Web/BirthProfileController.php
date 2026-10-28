<?php

namespace App\Http\Controllers\Web;

use App\Domain\Identity\BirthProfiles;
use App\Http\Controllers\Controller;
use App\Models\BirthProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BirthProfileController extends Controller
{
    public function __construct(private BirthProfiles $profiles) {}

    public function index(Request $request): View
    {
        return view('account.birth-profiles.index', ['profiles' => BirthProfile::where('user_id', $request->user()->id)->orderByDesc('is_primary')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('account.birth-profiles.form', ['profile' => new BirthProfile(['label' => 'self', 'time_accuracy' => 'exact']), 'placeLabel' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $p = $this->profiles->save($request->user(), $request->validate(BirthProfiles::rules()));

        return redirect()->route('account.birth-profiles.index')->with('status', 'Birth profile saved.')->with('tz_note', BirthProfiles::resolutionNote($p->tz_resolution));
    }

    public function edit(Request $request, BirthProfile $profile): View
    {
        $this->own($request, $profile);

        return view('account.birth-profiles.form', ['profile' => $profile, 'placeLabel' => $profile->place_label]);
    }

    public function update(Request $request, BirthProfile $profile): RedirectResponse
    {
        $this->own($request, $profile);
        $p = $this->profiles->save($request->user(), $request->validate(BirthProfiles::rules()), $profile);

        return redirect()->route('account.birth-profiles.index')->with('status', 'Birth profile updated.')->with('tz_note', BirthProfiles::resolutionNote($p->tz_resolution));
    }

    public function destroy(Request $request, BirthProfile $profile): RedirectResponse
    {
        $this->own($request, $profile);
        $this->profiles->delete($profile);

        return redirect()->route('account.birth-profiles.index')->with('status', 'Birth profile deleted.');
    }

    /** Owner check returns 404 (not 403) so other people's profile refs cannot be probed. */
    private function own(Request $request, BirthProfile $p): void
    {
        abort_unless($p->user_id === $request->user()->id, 404);
    }
}
