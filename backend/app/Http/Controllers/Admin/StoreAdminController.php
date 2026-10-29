<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Store\Shiprocket;
use App\Domain\Store\Store;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLogger;
use App\Support\Money;
use App\Support\Uploads\SafeImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StoreAdminController extends Controller
{
    public function products(): View
    {
        $this->authorize('catalogue.view');

        return view('admin.store.products', ['products' => DB::table('products')->orderBy('category')->orderBy('sort')->get()]);
    }

    public function edit(?string $ref = null): View
    {
        $this->authorize('catalogue.manage');
        $p = $ref ? DB::table('products')->where('ref', $ref)->first() : null;
        abort_if($ref && ! $p, 404);

        return view('admin.store.edit', ['p' => $p]);
    }

    public function save(Request $request, AuditLogger $audit, ?string $ref = null): RedirectResponse
    {
        $this->authorize('catalogue.manage');
        $p = $ref ? DB::table('products')->where('ref', $ref)->first() : null;
        $d = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'slug' => ['nullable', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:140'],
            'category' => ['required', Rule::in(array_keys(Store::CATEGORIES))], 'summary' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:5000'], 'weight' => ['nullable', 'string', 'max:60'], 'origin' => ['nullable', 'string', 'max:120'],
            'certification' => ['nullable', 'string', 'max:200'], 'price' => ['required', 'string', 'max:20'], 'stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'max_per_order' => ['required', 'integer', 'min:1', 'max:10'], 'active' => ['nullable', 'boolean'], 'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'image' => ['nullable', 'file', 'max:8192'],
            'ship_weight_g' => ['nullable', 'integer', 'min:10', 'max:30000'], 'ship_length_cm' => ['nullable', 'integer', 'min:1', 'max:150'],
            'ship_breadth_cm' => ['nullable', 'integer', 'min:1', 'max:150'], 'ship_height_cm' => ['nullable', 'integer', 'min:1', 'max:150'],
        ]);
        try {
            $price = Money::parseRupees($d['price']);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['price' => $e->getMessage()]);
        }
        if (! $price || $price < 100) {
            throw ValidationException::withMessages(['price' => 'Enter a price of at least ₹1.']);
        }
        $priceChanged = ! $p || (int) $p->price_minor !== $price;
        if ($priceChanged && ! $request->user()->can('prices.manage')) {
            throw ValidationException::withMessages(['price' => 'You do not have permission to change prices.']);
        }
        $slug = ($d['slug'] ?? null) ?: Str::slug($d['name']);
        if (DB::table('products')->where('slug', $slug)->when($p, fn ($q) => $q->where('id', '!=', $p->id))->exists()) {
            throw ValidationException::withMessages(['slug' => 'Another product already uses this web address.']);
        }
        $row = ['name' => $d['name'], 'slug' => $slug, 'category' => $d['category'], 'summary' => $d['summary'], 'description' => $d['description'] ?? null,
            'weight' => $d['weight'] ?? null, 'origin' => $d['origin'] ?? null, 'certification' => $d['certification'] ?? null, 'price_minor' => $price,
            'stock' => $d['stock'], 'max_per_order' => $d['max_per_order'], 'active' => (bool) ($d['active'] ?? false), 'sort' => (int) ($d['sort'] ?? 0), 'updated_at' => now(),
            'ship_weight_g' => (int) ($d['ship_weight_g'] ?? $p->ship_weight_g ?? 200), 'ship_length_cm' => (int) ($d['ship_length_cm'] ?? $p->ship_length_cm ?? 12),
            'ship_breadth_cm' => (int) ($d['ship_breadth_cm'] ?? $p->ship_breadth_cm ?? 10), 'ship_height_cm' => (int) ($d['ship_height_cm'] ?? $p->ship_height_cm ?? 6)];
        if ($request->hasFile('image')) {
            $row['image_path'] = 'uploads/'.SafeImage::store($request->file('image'), 'uploads', 'products', 'image');
        }
        if ($p) {
            DB::table('products')->where('id', $p->id)->update($row);
        } else {
            $row += ['ref' => (string) Str::ulid(), 'created_at' => now()];
            DB::table('products')->insert($row);
        }
        $audit->record('store.product_saved', null, ['product' => $slug, 'price_minor' => $price, 'stock' => $d['stock'], 'active' => $row['active']]);

        return redirect()->route('admin.store.products')->with('status', 'Product saved.');
    }

    public function orders(Request $request): View
    {
        $this->authorize('puja.fulfil');
        $status = (string) $request->query('status', 'awaiting_dispatch');

        return view('admin.store.orders', ['status' => $status, 'rows' => DB::table('shipments as s')->join('orders as o', 'o.id', '=', 's.order_id')->join('users as u', 'u.id', '=', 'o.user_id')
            ->when($status !== 'all', fn ($q) => $q->where('s.status', $status))->select('s.*', 'o.ref as order_ref', 'o.snapshot', 'o.status as order_status', 'u.name as customer')->orderByDesc('s.id')->paginate(30)->withQueryString()]);
    }

    public function order(string $ref, AuditLogger $audit): View
    {
        $this->authorize('puja.fulfil');
        $o = DB::table('orders')->where('ref', $ref)->where('kind', 'product')->first();
        abort_unless($o, 404);
        $s = DB::table('shipments')->where('order_id', $o->id)->first();
        $audit->record('store.order_viewed', null, ['order' => $ref]);

        return view('admin.store.order', ['o' => $o, 's' => $s, 'snap' => json_decode($o->snapshot, true), 'address' => Store::address($s),
            'courierReady' => app(Shiprocket::class)->configured()]);
    }

    public function bookCourier(Request $request, string $ref, Shiprocket $shiprocket): RedirectResponse
    {
        $this->authorize('puja.fulfil');
        $o = DB::table('orders')->where('ref', $ref)->where('kind', 'product')->first();
        abort_unless($o && in_array($o->status, ['paid', 'partially_refunded'], true), 404);
        $s = $shiprocket->book($o, $request->user());

        return back()->with('status', 'Courier booked: '.$s->courier.' AWB '.$s->awb.'. Pickup requested.');
    }

    public function updateShipment(Request $request, string $ref, Store $store): RedirectResponse
    {
        $this->authorize('puja.fulfil');
        $o = DB::table('orders')->where('ref', $ref)->where('kind', 'product')->first();
        abort_unless($o && in_array($o->status, ['paid', 'partially_refunded', 'refunded'], true), 404);
        $d = $request->validate(['status' => ['required', Rule::in(['awaiting_dispatch', 'dispatched', 'delivered', 'returned', 'cancelled'])], 'courier' => ['nullable', 'string', 'max:80'],
            'awb' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9\-]+$/'], 'tracking_url' => ['nullable', 'url:https', 'max:255']]);
        $store->updateShipment($o->id, $d, $request->user());

        return back()->with('status', 'Shipment updated.');
    }
}
