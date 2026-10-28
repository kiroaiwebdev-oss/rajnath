<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BirthProfile;
use App\Models\Enquiry;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('customers.view');
        $q = trim((string) $request->query('q', ''));
        $list = User::where('kind', User::KIND_CUSTOMER)
            ->when($q !== '', function ($w) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $w->where(fn ($x) => $x->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone_e164', 'like', $like));
            })->latest()->paginate(30)->withQueryString();

        return view('admin.customers.index', ['list' => $list, 'q' => $q]);
    }

    public function show(User $customer, AuditLogger $audit): View
    {
        $this->authorize('customers.view');
        abort_unless($customer->kind === User::KIND_CUSTOMER, 404);
        $audit->record('customer.viewed_by_staff', $customer);

        return view('admin.customers.show', [
            'c' => $customer,
            'profiles' => BirthProfile::where('user_id', $customer->id)->count(),
            'enquiries' => Enquiry::where('user_id', $customer->id)->latest()->limit(10)->get(),
            'devices' => $customer->tokens()->count() + DB::table('sessions')->where('user_id', $customer->id)->count(),
        ]);
    }

    public function setStatus(Request $request, User $customer, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('customers.restrict');
        abort_unless($customer->kind === User::KIND_CUSTOMER, 404);
        $data = $request->validate(['status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_SUSPENDED])], 'reason' => ['required', 'string', 'max:300']]);
        $customer->forceFill(['status' => $data['status']])->save();
        if ($data['status'] === User::STATUS_SUSPENDED) {
            DB::table('sessions')->where('user_id', $customer->id)->delete();
            $customer->tokens()->delete();
        }
        $audit->record('customer.status_changed', $customer, ['status' => $data['status'], 'reason' => $data['reason']]);

        return back()->with('status', 'Customer '.($data['status'] === 'active' ? 'reactivated' : 'suspended and signed out everywhere').'.');
    }
}
