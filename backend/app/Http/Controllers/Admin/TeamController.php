<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Identity\StaffInvitations;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): View
    {
        $this->authorize('team.view');
        $staff = User::query()->whereHas('roles')->with('roles')->orderBy('name')->paginate(25);
        $invitations = DB::table('staff_invitations')->whereNull('accepted_at')->whereNull('revoked_at')
            ->where('expires_at', '>', now())->orderByDesc('id')->get();

        return view('admin.team.index', ['staff' => $staff, 'invitations' => $invitations, 'roles' => $this->assignableRoles()]);
    }

    public function invite(Request $request, StaffInvitations $inv): RedirectResponse
    {
        $this->authorize('team.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in($this->assignableRoles())],
        ]);
        [, $token] = $inv->create($data['email'], $data['name'], $data['roles'], $request->user());

        // Link is displayed once to the inviter; email delivery is added with the SMTP provider (phase 3).
        return back()->with('invite_link', url('/staff/invitation/'.$token));
    }

    public function revokeInvitation(Request $request, string $ref, StaffInvitations $inv): RedirectResponse
    {
        $this->authorize('team.manage');
        $inv->revoke($ref, $request->user());

        return back()->with('status', 'Invitation revoked.');
    }

    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        $this->authorize('team.manage');
        $data = $request->validate(['roles' => ['array'], 'roles.*' => [Rule::in($this->assignableRoles())]]);
        $new = $data['roles'] ?? [];
        $this->guardOwnerChanges($request->user(), $user, $new);
        $before = $user->getRoleNames()->all();
        $user->syncRoles($new);
        $this->audit->record('staff.roles_changed', $user, ['before' => $before, 'after' => $new]);

        return back()->with('status', 'Roles updated for '.$user->name.'.');
    }

    public function setStatus(Request $request, User $user): RedirectResponse
    {
        $this->authorize('team.manage');
        $data = $request->validate(['status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_SUSPENDED])]]);
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['status' => 'You cannot change your own status.']);
        }
        if ($user->hasRole('owner') && ! $request->user()->hasRole('owner')) {
            abort(403);
        }
        if ($data['status'] === User::STATUS_SUSPENDED && $user->hasRole('owner') && $this->activeOwners() <= 1) {
            throw ValidationException::withMessages(['status' => 'The last active owner cannot be suspended.']);
        }
        $user->forceFill(['status' => $data['status']])->save();
        if ($data['status'] === User::STATUS_SUSPENDED) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->tokens()->delete();
        }
        $this->audit->record('staff.status_changed', $user, ['status' => $data['status']]);

        return back()->with('status', 'Status updated.');
    }

    private function guardOwnerChanges(User $actor, User $target, array $newRoles): void
    {
        $touchesOwner = $target->hasRole('owner') || in_array('owner', $newRoles, true);
        if ($touchesOwner && ! $actor->hasRole('owner')) {
            abort(403, 'Only an owner can change owner roles.');
        }
        if ($target->hasRole('owner') && ! in_array('owner', $newRoles, true) && $this->activeOwners() <= 1) {
            throw ValidationException::withMessages(['roles' => 'The last owner cannot lose the owner role.']);
        }
    }

    private function activeOwners(): int
    {
        return User::role('owner')->where('status', User::STATUS_ACTIVE)->count();
    }

    private function assignableRoles(): array
    {
        return array_values(array_diff(array_keys(config('rbac.roles')), ['practitioner']));
    }
}
