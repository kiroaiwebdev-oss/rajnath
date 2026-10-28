<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Offering;
use App\Models\OfferingVariant;
use App\Models\ServiceFamily;
use App\Support\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CatalogueController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $this->authorize('catalogue.view');
        $status = (string) $request->query('status', '');
        $family = (string) $request->query('family', '');
        $q = trim((string) $request->query('q', ''));
        $offerings = Offering::with(['translations', 'family', 'variants'])
            ->when($status !== '', fn ($w) => $w->where('status', $status))
            ->when($family !== '', fn ($w) => $w->whereHas('family', fn ($f) => $f->where('code', $family)))
            ->when($q !== '', fn ($w) => $w->whereHas('translations', fn ($t) => $t->where('title', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->orderBy('family_id')->orderBy('sort')->paginate(30)->withQueryString();

        return view('admin.catalogue.index', ['offerings' => $offerings, 'families' => ServiceFamily::orderBy('sort')->get(), 'status' => $status, 'family' => $family, 'q' => $q]);
    }

    public function create(): View
    {
        $this->authorize('catalogue.manage');

        return view('admin.catalogue.edit', ['o' => new Offering(['status' => 'draft']), 't' => null, 'families' => ServiceFamily::orderBy('sort')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('catalogue.manage');
        $data = $this->validated($request);
        $o = DB::transaction(function () use ($data) {
            $o = Offering::create(['family_id' => $data['family_id'], 'slug' => $data['slug'], 'status' => 'draft', 'featured' => $data['featured'], 'sort' => $data['sort'], 'image_path' => $data['image_path']]);
            $o->translations()->create($this->translationData($data));
            $o->variants()->create(['name' => 'Standard', 'mode' => null]);

            return $o;
        });
        $this->audit->record('catalogue.offering_created', $o, ['slug' => $o->slug]);

        return redirect()->route('admin.catalogue.edit', $o->ref)->with('status', 'Created as a draft. Add variants and prices, then activate it.');
    }

    public function edit(string $ref): View
    {
        $this->authorize('catalogue.view');
        $o = Offering::with(['translations', 'variants', 'family'])->where('ref', $ref)->firstOrFail();

        return view('admin.catalogue.edit', ['o' => $o, 't' => $o->t('en'), 'families' => ServiceFamily::orderBy('sort')->get()]);
    }

    public function update(Request $request, string $ref): RedirectResponse
    {
        $this->authorize('catalogue.manage');
        $o = Offering::where('ref', $ref)->firstOrFail();
        $data = $this->validated($request, $o);
        DB::transaction(function () use ($o, $data) {
            $o->fill(['family_id' => $data['family_id'], 'slug' => $data['slug'], 'featured' => $data['featured'], 'sort' => $data['sort'], 'image_path' => $data['image_path']]);
            $o->updated_by = auth()->id();
            $o->save();
            $o->translations()->updateOrCreate(['locale' => 'en'], $this->translationData($data));
        });
        $this->audit->record('catalogue.offering_updated', $o, ['slug' => $o->slug]);

        return back()->with('status', 'Saved.');
    }

    public function setStatus(Request $request, string $ref): RedirectResponse
    {
        $this->authorize('catalogue.manage');
        $o = Offering::with(['family', 'variants', 'translations'])->where('ref', $ref)->firstOrFail();
        $status = $request->validate(['status' => ['required', Rule::in(array_keys(Offering::STATUSES))]])['status'];
        if ($status === 'active') {
            if ($o->variants->where('active', true)->isEmpty()) {
                throw ValidationException::withMessages(['status' => 'Add at least one active variant before activating.']);
            }
            if (in_array($o->family->code, ['puja', 'chadhava'], true) && ! \App\Domain\Puja\PujaBookings::canPublish($o->id, $o->slug)) {
                throw ValidationException::withMessages(['status' => 'Link at least one priest/temple with a verified, current authorisation (Admin → Priests & temples) before publishing this puja.']);
            }
            if (! $o->t('en')?->title || ! $o->t('en')?->summary) {
                throw ValidationException::withMessages(['status' => 'Add a title and summary before activating.']);
            }
        }
        $before = $o->status;
        $o->forceFill(['status' => $status, 'activated_at' => $status === 'active' ? ($o->activated_at ?? now()) : $o->activated_at, 'updated_by' => auth()->id()])->save();
        $this->audit->record('catalogue.status_changed', $o, ['from' => $before, 'to' => $status]);

        return back()->with('status', 'Status changed to '.Offering::STATUSES[$status].'.');
    }

    /** Variants and prices. Price changes require prices.manage + step-up (route middleware). */
    public function saveVariant(Request $request, string $ref): RedirectResponse
    {
        $this->authorize('prices.manage');
        $o = Offering::where('ref', $ref)->firstOrFail();
        $data = $request->validate([
            'variant' => ['nullable', 'string', 'size:26'],
            'name' => ['required', 'string', 'max:120'],
            'mode' => ['nullable', Rule::in(array_keys(OfferingVariant::MODES))],
            'duration_min' => ['nullable', 'integer', 'min:5', 'max:600'],
            'participants' => ['required', 'integer', 'min:1', 'max:10'],
            'price' => ['nullable', 'string', 'max:20'],
            'urgent' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ]);
        try {
            $price = Money::parseRupees($data['price'] ?? null);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['price' => $e->getMessage()]);
        }
        $v = $data['variant'] ? $o->variants()->where('ref', $data['variant'])->firstOrFail() : new OfferingVariant;
        $before = $v->exists ? ['price_minor' => $v->price_minor, 'active' => $v->active] : null;
        $v->fill(['name' => $data['name'], 'mode' => $data['mode'] ?? null, 'duration_min' => $data['duration_min'] ?? null,
            'participants' => $data['participants'], 'urgent' => (bool) ($data['urgent'] ?? false), 'price_minor' => $price,
            'active' => (bool) ($data['active'] ?? false), 'currency' => 'INR']);
        $v->offering_id = $o->id;
        $v->save();
        $this->audit->record('catalogue.variant_saved', $o, ['variant' => $v->ref, 'before' => $before, 'price_minor' => $price, 'active' => $v->active]);

        return back()->with('status', 'Variant saved'.($price === null ? ' (shown as "Price on request").' : ' at '.Money::format($price).'.'));
    }

    private function validated(Request $request, ?Offering $o = null): array
    {
        $data = $request->validate([
            'family_id' => ['required', 'exists:service_families,id'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['required', 'string', 'max:300'],
            'body' => ['nullable', 'string', 'max:10000'],
            'includes' => ['nullable', 'string', 'max:4000'],
            'audience' => ['nullable', 'string', 'max:4000'],
            'exclusions' => ['nullable', 'string', 'max:2000'],
            'disclaimer' => ['nullable', 'string', 'max:2000'],
            'image_path' => ['nullable', 'string', 'max:255', 'regex:#^images/[a-z0-9/_\-]+\.(jpg|jpeg|png|webp)$#'],
            'featured' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $data += ['slug' => null, 'image_path' => null, 'body' => null, 'includes' => null, 'audience' => null, 'exclusions' => null, 'disclaimer' => null];
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        if (Offering::where('slug', $data['slug'])->when($o, fn ($q) => $q->whereKeyNot($o->id))->exists()) {
            throw ValidationException::withMessages(['slug' => 'Another service already uses this web address.']);
        }
        if ($data['image_path'] && ! is_file(public_path($data['image_path']))) {
            throw ValidationException::withMessages(['image_path' => 'Image not found on the server.']);
        }
        $data['featured'] = (bool) ($data['featured'] ?? false);
        $data['sort'] = (int) ($data['sort'] ?? 0);

        return $data;
    }

    private function translationData(array $d): array
    {
        $lines = fn (?string $s) => $s ? array_values(array_filter(array_map('trim', preg_split('/\R/', $s)))) : null;

        return ['title' => $d['title'], 'summary' => $d['summary'], 'body' => $d['body'] ?? null, 'includes' => $lines($d['includes'] ?? null),
            'audience' => $lines($d['audience'] ?? null), 'exclusions' => $d['exclusions'] ?? null, 'disclaimer' => $d['disclaimer'] ?? null,
            'meta_description' => mb_substr($d['summary'], 0, 300)];
    }
}
