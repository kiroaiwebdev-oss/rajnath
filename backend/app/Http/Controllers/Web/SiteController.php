<?php

namespace App\Http\Controllers\Web;

use App\Domain\Places\PlaceSearch;
use App\Domain\Showcase\Gallery;
use App\Domain\Showcase\Profile;
use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\Offering;
use App\Models\OfferingVariant;
use App\Models\ServiceFamily;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function home(): View
    {
        return view('public.home', [
            'featured' => Offering::public()->with(['translations', 'variants', 'family'])->where('featured', true)->orderBy('sort')->limit(6)->get(),
            'families' => ServiceFamily::whereHas('offerings', fn ($q) => $q->where('status', 'active'))->orderBy('sort')->withCount(['offerings' => fn ($q) => $q->where('status', 'active')])->get(),
            'profile' => $profile = app(Profile::class),
            'highlights' => $profile->homeHighlights(),
            'albums' => app(Gallery::class)->publicAlbums(null, 3, true),
            'awardPhotos' => ShowcaseController::awardPhotos(2),
        ]);
    }

    public function services(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $family = (string) $request->query('family', '');
        $mode = (string) $request->query('mode', '');
        $offerings = Offering::public()->with(['translations', 'variants', 'family'])
            ->when($family !== '', fn ($w) => $w->whereHas('family', fn ($f) => $f->where('slug', $family)))
            ->when($mode !== '', fn ($w) => $w->whereHas('variants', fn ($v) => $v->where('mode', $mode)->where('active', true)))
            ->when($q !== '', function ($w) use ($q) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q).'%';
                $w->whereHas('translations', fn ($t) => $t->where('title', 'like', $like)->orWhere('summary', 'like', $like)->orWhere('body', 'like', $like));
            })
            ->orderBy('sort')->get();

        return view('public.services.index', [
            'offerings' => $offerings, 'q' => $q, 'family' => $family, 'mode' => $mode,
            'families' => ServiceFamily::whereHas('offerings', fn ($w) => $w->where('status', 'active'))->orderBy('sort')->get(),
        ]);
    }

    public function service(string $slug): View
    {
        $o = Offering::public()->with(['translations', 'variants', 'family'])->where('slug', $slug)->firstOrFail();

        return view('public.services.show', [
            'o' => $o, 't' => $o->t(),
            'related' => Offering::public()->with(['translations', 'variants', 'family'])->where('family_id', $o->family_id)->whereKeyNot($o->id)->orderBy('sort')->limit(3)->get(),
        ]);
    }

    public function enquire(Request $request, AuditLogger $audit): RedirectResponse
    {
        // Honeypot: real people never fill the hidden "website" field.
        if (filled($request->input('website'))) {
            return back()->with('status', 'Thank you. We will contact you shortly.');
        }
        $data = $request->validate([
            'offering' => ['nullable', 'string', 'exists:offerings,slug'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:190'],
            'mode' => ['nullable', Rule::in(array_keys(OfferingVariant::MODES))],
            'language' => ['nullable', Rule::in(['Hindi', 'Maithili', 'Sanskrit', 'English'])],
            'preferred_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'birth_time' => ['nullable', 'date_format:H:i'],
            'birth_place' => ['nullable', 'string', 'max:200'],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'Please agree to be contacted about your request.']);
        $digits = preg_replace('/[\s\-()]/', '', $data['phone']);
        $phone = preg_match('/^(?:\+?91|0)?([6-9]\d{9})$/', $digits, $m) ? '+91'.$m[1] : (preg_match('/^\+[1-9]\d{7,14}$/', $digits) ? $digits : null);
        if (! $phone) {
            return back()->withInput()->withErrors(['phone' => 'Enter a 10-digit mobile number, or include the country code.']);
        }
        $e = Enquiry::create([
            'user_id' => $request->user()?->id,
            'offering_id' => $data['offering'] ? Offering::where('slug', $data['offering'])->value('id') : null,
            'name' => $data['name'], 'phone_e164' => $phone, 'email' => $data['email'] ?? null,
            'mode' => $data['mode'] ?? null, 'language' => $data['language'] ?? null, 'preferred_date' => $data['preferred_date'] ?? null,
            'birth_date' => $data['birth_date'] ?? null, 'birth_time' => $data['birth_time'] ?? null, 'birth_place' => $data['birth_place'] ?? null,
            'message' => $data['message'] ?? null, 'ip' => $request->ip(),
        ]);
        $audit->record('enquiry.created', $e, ['offering' => $data['offering'] ?? null], $request->user(), $request->user() ? null : 'guest');

        return redirect()->route('enquiry.thanks', $e->ref);
    }

    public function thanks(string $ref): View
    {
        $e = Enquiry::with('offering.translations')->where('ref', $ref)->where('created_at', '>', now()->subHour())->firstOrFail();

        return view('public.enquiry-thanks', ['e' => $e]);
    }

    public function places(Request $request, PlaceSearch $search): JsonResponse
    {
        return response()->json(['data' => $search->search((string) $request->query('q', ''))]);
    }

    public function faq(): View
    {
        $faqs = DB::table('faqs')->where('published', true)->where('locale', app()->getLocale())->orderBy('sort')->get();

        return view('public.faq', ['faqs' => $faqs->isNotEmpty() ? $faqs : DB::table('faqs')->where('published', true)->where('locale', 'en')->orderBy('sort')->get()]);
    }

    public function manifest(): JsonResponse
    {
        return response()->json([
            'name' => config('brand')['brand.name'], 'short_name' => 'Rajnath Jha', 'start_url' => '/?source=pwa', 'scope' => '/',
            'display' => 'standalone', 'background_color' => '#fdfbf6', 'theme_color' => '#141a3c', 'lang' => 'en-IN',
            'description' => 'Vedic astrology, Vastu and remedies with Acharya Dr. Rajnath Jha.',
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }
}
