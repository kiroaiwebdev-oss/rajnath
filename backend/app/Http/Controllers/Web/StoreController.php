<?php

namespace App\Http\Controllers\Web;

use App\Domain\Payments\Razorpay;
use App\Domain\Store\Store;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class StoreController extends Controller
{
    public function index(Request $request): View
    {
        $cat = (string) $request->query('category', '');

        return view('public.store.index', [
            'products' => DB::table('products')->where('active', true)->when($cat !== '', fn ($q) => $q->where('category', $cat))->orderBy('sort')->orderBy('name')->get(),
            'category' => $cat,
        ]);
    }

    public function show(string $slug, Razorpay $rzp): View
    {
        $p = DB::table('products')->where('slug', $slug)->where('active', true)->first();
        abort_unless($p, 404);

        return view('public.store.show', ['p' => $p, 'payable' => $rzp->configured()]);
    }

    public function buy(Request $request, Store $store): RedirectResponse
    {
        $d = $request->validate(Store::addressRules() + [
            'product' => ['required', 'string', 'size:26'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'key' => ['required', 'string', 'max:64'],
            'agree' => ['accepted'],
        ], ['ship_pincode.regex' => 'Enter a valid 6-digit PIN code.', 'ship_phone.regex' => 'Enter a 10-digit Indian mobile number.', 'agree.accepted' => 'Please confirm you have read the gemstone note.']);
        $address = ['name' => $d['ship_name'], 'phone' => $d['ship_phone'], 'line1' => $d['ship_line1'], 'line2' => $d['ship_line2'] ?? null,
            'city' => $d['ship_city'], 'state' => $d['ship_state'], 'pincode' => $d['ship_pincode'], 'country' => 'India'];
        try {
            $r = $store->buy($request->user(), $d['product'], (int) $d['quantity'], $address, $d['key']);
        } catch (RuntimeException $e) {
            report($e);

            return back()->withInput()->withErrors(['product' => 'Online payment is not available right now. Please try again later.']);
        }

        return redirect()->route('checkout.show', $r['order']->ref);
    }
}
