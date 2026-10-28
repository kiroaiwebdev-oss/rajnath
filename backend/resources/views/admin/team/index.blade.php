<x-layouts.admin title="Team & permissions">
    @if (session('invite_link'))
        <div class="card mb-6 border-gold-200 bg-gold-50 p-4" role="status">
            <p class="text-sm font-semibold">Invitation created. Share this one-time link securely (shown only once, valid 48 hours):</p>
            <code class="mt-2 block break-all rounded-lg bg-white p-2 text-xs">{{ session('invite_link') }}</code>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <section aria-labelledby="staff-h" class="min-w-0">
            <h2 id="staff-h" class="text-base">Staff</h2>
            <div class="table-wrap mt-3" role="region" aria-label="Staff members" tabindex="0">
                <table class="table">
                    <thead><tr><th scope="col">Name</th><th scope="col">Roles</th><th scope="col">Status</th><th scope="col">MFA</th>@can('team.manage')<th scope="col"><span class="sr-only">Actions</span></th>@endcan</tr></thead>
                    <tbody>
                    @foreach ($staff as $member)
                        <tr>
                            <td><span class="font-medium">{{ $member->name }}</span><br><span class="text-xs text-ink-600">{{ $member->email }}</span></td>
                            <td>@foreach ($member->roles as $r)<span class="badge-ink mr-1">{{ str_replace('_', ' ', $r->name) }}</span>@endforeach</td>
                            <td>@if ($member->isActive())<span class="badge-success">Active</span>@else<span class="badge-danger">{{ ucfirst($member->status) }}</span>@endif</td>
                            <td>{{ $member->hasMfa() ? 'On' : 'Off' }}</td>
                            @can('team.manage')
                            <td class="min-w-60">
                                <details>
                                    <summary class="cursor-pointer text-sm">Manage</summary>
                                    <form method="POST" action="{{ route('admin.team.roles', $member) }}" class="mt-3 space-y-2">
                                        @csrf @method('PUT')
                                        @foreach ($roles as $role)
                                            <label class="flex min-h-9 items-center gap-2 text-sm"><input type="checkbox" name="roles[]" value="{{ $role }}" @checked($member->hasRole($role)) class="size-4 accent-ink-900"> {{ str_replace('_', ' ', $role) }}</label>
                                        @endforeach
                                        <button class="btn-outline">Save roles</button>
                                    </form>
                                    @unless ($member->is(auth()->user()))
                                    <form method="POST" action="{{ route('admin.team.status', $member) }}" class="mt-3">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="status" value="{{ $member->isActive() ? 'suspended' : 'active' }}">
                                        <button class="{{ $member->isActive() ? 'btn-danger' : 'btn-outline' }}">{{ $member->isActive() ? 'Suspend & sign out' : 'Reactivate' }}</button>
                                    </form>
                                    @endunless
                                </details>
                            </td>
                            @endcan
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $staff->links() }}</div>
        </section>

        @can('team.manage')
        <section aria-labelledby="invite-h" class="card h-fit p-5">
            <h2 id="invite-h" class="text-base">Invite staff member</h2>
            <form method="POST" action="{{ route('admin.team.invite') }}" class="mt-4 space-y-4">
                @csrf
                <x-field name="name" label="Full name" required />
                <x-field name="email" label="Email" type="email" required />
                <fieldset>
                    <legend class="field-label">Roles</legend>
                    @foreach ($roles as $role)
                        <label class="flex min-h-9 items-center gap-2 text-sm"><input type="checkbox" name="roles[]" value="{{ $role }}" @checked(in_array($role, old('roles', []))) class="size-4 accent-ink-900"> {{ str_replace('_', ' ', $role) }}</label>
                    @endforeach
                </fieldset>
                <button class="btn-primary w-full">Create invitation</button>
            </form>

            @if ($invitations->isNotEmpty())
                <h3 class="mt-6 text-sm">Pending invitations</h3>
                <ul class="mt-2 space-y-2 text-sm">
                    @foreach ($invitations as $i)
                        <li class="flex items-center justify-between gap-2">
                            <span class="min-w-0 truncate">{{ $i->email }}</span>
                            <form method="POST" action="{{ route('admin.team.invitations.revoke', $i->ref) }}">@csrf @method('DELETE')<button class="btn-ghost px-3 text-danger-700">Revoke</button></form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
        @endcan
    </div>
</x-layouts.admin>
