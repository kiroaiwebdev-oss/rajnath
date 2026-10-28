<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Booking\Slots;
use App\Domain\Puja\PujaBookings;
use App\Domain\Reports\ReportJobs;
use App\Domain\Support\Tickets;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Admin: availability & bookings, suppliers & puja fulfilment, report writing, support, Rashifal. */
class OperationsController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    private function row(string $table, string $ref): object
    {
        $r = DB::table($table)->where('ref', $ref)->first();
        abort_unless($r, 404);

        return $r;
    }

    // ---------- Availability & bookings ----------
    public function bookings(Request $request): View
    {
        $this->authorize('bookings.view');

        return view('admin.ops.bookings', [
            'rules' => DB::table('availability_rules')->orderBy('weekday')->orderBy('start_local')->get(),
            'leave' => DB::table('availability_exceptions')->where('date', '>=', now('Asia/Kolkata')->toDateString())->orderBy('date')->get(),
            'upcoming' => DB::table('bookings as b')->join('users as u', 'u.id', '=', 'b.user_id')->join('offering_variants as v', 'v.id', '=', 'b.variant_id')
                ->whereIn('b.status', ['confirmed'])->orderBy('b.start_utc')->select('b.*', 'u.name as customer', 'v.name as variant')->limit(100)->get(),
        ]);
    }

    public function saveRule(Request $request): RedirectResponse
    {
        $this->authorize('bookings.manage');
        $d = $request->validate(['weekday' => ['required', 'integer', 'between:0,6'], 'start_local' => ['required', 'date_format:H:i'], 'end_local' => ['required', 'date_format:H:i', 'after:start_local'], 'slot_minutes' => ['required', Rule::in([15, 30, 45, 60])]]);
        DB::table('availability_rules')->insert($d + ['active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record('booking.rule_added', null, $d);

        return back()->with('status', 'Working hours added.');
    }

    public function deleteRule(int $id): RedirectResponse
    {
        $this->authorize('bookings.manage');
        DB::table('availability_rules')->where('id', $id)->delete();
        $this->audit->record('booking.rule_deleted', null, ['id' => $id]);

        return back()->with('status', 'Removed. Existing bookings are not affected.');
    }

    public function addLeave(Request $request): RedirectResponse
    {
        $this->authorize('bookings.manage');
        $d = $request->validate(['date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'unique:availability_exceptions,date'], 'reason' => ['nullable', 'string', 'max:120']]);
        DB::table('availability_exceptions')->insert($d + ['created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Leave day added. New bookings are blocked on that date; check existing bookings below.');
    }

    public function updateBooking(Request $request, string $ref, Slots $slots): RedirectResponse
    {
        $this->authorize('bookings.manage');
        $d = $request->validate(['status' => ['nullable', Rule::in(['completed', 'no_show'])], 'meeting_link' => ['nullable', 'url:https', 'max:255'], 'provider' => ['nullable', Rule::in(['livekit', 'zoom', 'google_meet'])]]);
        if (! empty($d['provider'])) {
            return $this->switchProvider($this->row('bookings', $ref), $d['provider'], $request->user());
        }
        $slots->setOutcome($this->row('bookings', $ref), $d['status'] ?? 'link', $d['meeting_link'] ?? null, $request->user());

        return back()->with('status', 'Booking updated.');
    }

    // ---------- Suppliers ----------
    public function suppliers(): View
    {
        $this->authorize('suppliers.manage');

        return view('admin.ops.suppliers', ['suppliers' => DB::table('suppliers')->orderBy('name')->get(),
            'offerings' => DB::table('offerings')->join('service_families as f', 'f.id', '=', 'offerings.family_id')->join('offering_translations as t', fn ($j) => $j->on('t.offering_id', '=', 'offerings.id')->where('t.locale', 'en'))
                ->whereIn('f.code', ['puja', 'chadhava'])->orderBy('t.title')->get(['offerings.id', 't.title']),
            'links' => DB::table('offering_supplier')->get()->groupBy('supplier_id')->map->pluck('offering_id')]);
    }

    public function saveSupplier(Request $request): RedirectResponse
    {
        $this->authorize('suppliers.manage');
        $d = $request->validate(['id' => ['nullable', 'integer', 'exists:suppliers,id'], 'kind' => ['required', Rule::in(['priest', 'temple', 'vendor'])], 'name' => ['required', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:120'], 'phone' => ['nullable', 'string', 'max:20'], 'city' => ['nullable', 'string', 'max:100'],
            'authorized_until' => ['nullable', 'date_format:Y-m-d'], 'active' => ['nullable', 'boolean'], 'authorization' => ['nullable', 'file', 'max:10240'],
            'offerings' => ['nullable', 'array'], 'offerings.*' => ['integer', 'exists:offerings,id']]);
        $row = ['kind' => $d['kind'], 'name' => $d['name'], 'contact_name' => $d['contact_name'] ?? null, 'phone' => $d['phone'] ?? null, 'city' => $d['city'] ?? null,
            'authorized_until' => $d['authorized_until'] ?? null, 'active' => (bool) ($d['active'] ?? false), 'updated_at' => now()];
        if ($request->hasFile('authorization')) {
            // Uploading the signed authorisation records who verified it and when.
            $row['authorization_doc'] = PujaBookings::storeAuthorization($request->file('authorization'));
            $row['verified_by'] = $request->user()->id;
            $row['verified_at'] = now();
        }
        $id = $d['id'] ?? null;
        DB::transaction(function () use (&$id, $row, $d) {
            if ($id) {
                DB::table('suppliers')->where('id', $id)->update($row);
            } else {
                $id = DB::table('suppliers')->insertGetId($row + ['ref' => (string) Str::ulid(), 'created_at' => now()]);
            }
            DB::table('offering_supplier')->where('supplier_id', $id)->delete();
            foreach ($d['offerings'] ?? [] as $oid) {
                DB::table('offering_supplier')->insert(['offering_id' => $oid, 'supplier_id' => $id]);
            }
        });
        $this->audit->record('supplier.saved', null, ['supplier' => $id, 'offerings' => count($d['offerings'] ?? []), 'authorization_uploaded' => $request->hasFile('authorization')]);

        return back()->with('status', 'Priest / temple saved.');
    }

    // ---------- Puja fulfilment ----------
    public function pujas(Request $request): View
    {
        $this->authorize('puja.fulfil');
        $status = (string) $request->query('status', 'requested');

        return view('admin.ops.pujas', ['status' => $status, 'rows' => DB::table('puja_bookings as p')->join('users as u', 'u.id', '=', 'p.user_id')->join('offering_translations as t', fn ($j) => $j->on('t.offering_id', '=', 'p.offering_id')->where('t.locale', 'en'))
            ->when($status !== 'all', fn ($q) => $q->where('p.status', $status))->select('p.*', 'u.name as customer', 't.title')->orderBy('p.preferred_date')->paginate(30)->withQueryString()]);
    }

    public function puja(string $ref): View
    {
        $this->authorize('puja.fulfil');
        $b = $this->row('puja_bookings', $ref);
        $this->audit->record('puja.viewed', null, ['booking' => $ref]);

        return view('admin.ops.puja', ['b' => $b, 'sankalp' => PujaBookings::sankalp($b), 'evidence' => $b->evidence ? json_decode($b->evidence, true) : null,
            'title' => DB::table('offering_translations')->where('offering_id', $b->offering_id)->where('locale', 'en')->value('title'), 'customer' => DB::table('users')->find($b->user_id),
            'suppliers' => DB::table('suppliers')->join('offering_supplier', 'offering_supplier.supplier_id', '=', 'suppliers.id')->where('offering_supplier.offering_id', $b->offering_id)->get(['suppliers.*'])->filter(fn ($s) => PujaBookings::supplierOk($s))]);
    }

    public function pujaAction(Request $request, string $ref, PujaBookings $puja): RedirectResponse
    {
        $this->authorize('puja.fulfil');
        $b = $this->row('puja_bookings', $ref);
        $a = $request->validate(['action' => ['required', Rule::in(['schedule', 'performed', 'complete', 'cancel'])]])['action'];
        match ($a) {
            'schedule' => (function () use ($request, $b, $puja) {
                $d = $request->validate(['supplier' => ['required', 'integer'], 'scheduled_at' => ['required', 'date_format:Y-m-d\TH:i'], 'live_link' => ['nullable', 'url:https', 'max:255'], 'note' => ['nullable', 'string', 'max:500']]);
                $puja->schedule($b, (int) $d['supplier'], $d['scheduled_at'], $d['live_link'] ?? null, $d['note'] ?? null, $request->user());
            })(),
            'performed' => (function () use ($request, $b, $puja) {
                $d = $request->validate(['photos' => ['required', 'array', 'min:1', 'max:10'], 'photos.*' => ['file', 'max:8192'], 'video_url' => ['nullable', 'url:https', 'max:255'], 'note' => ['nullable', 'string', 'max:500']]);
                $puja->markPerformed($b, $request->file('photos'), $d['video_url'] ?? null, $d['note'] ?? null, $request->user());
            })(),
            'complete' => $puja->complete($b, $request->user()),
            'cancel' => $puja->cancel($b, $request->validate(['reason' => ['required', 'string', 'max:300']])['reason'], $request->user()),
        };

        return back()->with('status', 'Puja updated.'.($a === 'cancel' ? ' Issue the refund from Finance › order.' : ''));
    }

    // ---------- Reports ----------
    public function reports(Request $request): View
    {
        $this->authorize('reports.fulfil');
        $status = (string) $request->query('status', 'in_preparation');

        return view('admin.ops.reports', ['status' => $status, 'rows' => DB::table('report_jobs as j')->join('users as u', 'u.id', '=', 'j.user_id')
            ->when($status !== 'all', fn ($q) => $q->where('j.status', $status))->select('j.*', 'u.name as customer')->orderBy('j.updated_at')->paginate(30)->withQueryString()]);
    }

    public function report(string $ref): View
    {
        $this->authorize('reports.fulfil');
        $j = $this->row('report_jobs', $ref);

        return view('admin.ops.report', ['j' => $j, 'defs' => ReportJobs::sections($j->offering_slug), 'sections' => $j->sections ? json_decode($j->sections, true) : [],
            'chart' => $j->chart ? json_decode($j->chart, true) : null, 'subjects' => $j->subjects ? json_decode($j->subjects, true) : []]);
    }

    public function saveReport(Request $request, string $ref, ReportJobs $jobs): RedirectResponse
    {
        $this->authorize('reports.fulfil');
        $d = $request->validate(['sections' => ['array'], 'sections.*' => ['nullable', 'string', 'max:8000'], 'action' => ['required', 'in:draft,complete']]);
        $jobs->save($this->row('report_jobs', $ref), $d['sections'] ?? [], $d['action'] === 'complete', $request->user());

        return back()->with('status', $d['action'] === 'complete' ? 'Report published to the customer.' : 'Draft saved.');
    }

    // ---------- Support ----------
    public function tickets(Request $request): View
    {
        $this->authorize('support.view');
        $status = (string) $request->query('status', 'open');

        return view('admin.ops.tickets', ['status' => $status, 'rows' => DB::table('tickets as t')->join('users as u', 'u.id', '=', 't.user_id')
            ->when($status !== 'all', fn ($q) => $q->where('t.status', $status))->select('t.*', 'u.name as customer')->orderByRaw("priority = 'high' desc")->orderBy('t.updated_at')->paginate(30)->withQueryString()]);
    }

    public function ticket(string $ref): View
    {
        $this->authorize('support.view');
        $t = $this->row('tickets', $ref);

        return view('admin.ops.ticket', ['t' => $t, 'customer' => DB::table('users')->find($t->user_id), 'order' => $t->order_id ? DB::table('orders')->find($t->order_id) : null,
            'messages' => DB::table('ticket_messages as m')->join('users as u', 'u.id', '=', 'm.author_id')->where('m.ticket_id', $t->id)->orderBy('m.id')->get(['m.*', 'u.name', 'u.kind'])]);
    }

    public function ticketReply(Request $request, string $ref, Tickets $tickets): RedirectResponse
    {
        $this->authorize('support.handle');
        $d = $request->validate(['body' => ['required', 'string', 'max:5000'], 'internal' => ['nullable', 'boolean'], 'status' => ['nullable', Rule::in(array_keys(Tickets::STATUSES))]]);
        $tickets->reply($this->row('tickets', $ref), $request->user(), $d['body'], (bool) ($d['internal'] ?? false), $d['status'] ?? null);

        return back()->with('status', 'Saved.');
    }

    // ---------- Rashifal (author → reviewer/publisher, different people) ----------
    public function horoscopes(Request $request): View
    {
        $this->authorize('content.author');

        return view('admin.ops.horoscopes', ['rows' => DB::table('horoscopes')->orderByDesc('starts_on')->orderBy('sign')->paginate(50)]);
    }

    public function saveHoroscope(Request $request): RedirectResponse
    {
        $this->authorize('content.author');
        $d = $request->validate(['period' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])], 'sign' => ['required', 'integer', 'between:0,11'],
            'starts_on' => ['required', 'date_format:Y-m-d'], 'locale' => ['required', Rule::in(['hi', 'en'])], 'body' => ['required', 'string', 'min:40', 'max:5000']]);
        $start = \Carbon\CarbonImmutable::parse($d['starts_on']);
        $end = match ($d['period']) { 'daily' => $start, 'weekly' => $start->addDays(6), 'monthly' => $start->endOfMonth(), 'yearly' => $start->endOfYear() };
        $existing = DB::table('horoscopes')->where(['period' => $d['period'], 'sign' => $d['sign'], 'starts_on' => $d['starts_on'], 'locale' => $d['locale']])->first();
        if ($existing && $existing->status === 'published' && ! $request->user()->can('content.publish')) {
            throw ValidationException::withMessages(['body' => 'This entry is published; ask a publisher to change it.']);
        }
        $row = ['ends_on' => $end->toDateString(), 'body' => $d['body'], 'status' => 'draft', 'author_id' => $request->user()->id, 'reviewer_id' => null, 'published_at' => null, 'updated_at' => now()];
        if ($existing) {
            DB::table('horoscopes')->where('id', $existing->id)->update($row + ['version' => $existing->version + 1]);
        } else {
            DB::table('horoscopes')->insert($row + ['period' => $d['period'], 'sign' => $d['sign'], 'starts_on' => $d['starts_on'], 'locale' => $d['locale'], 'created_at' => now()]);
        }
        $this->audit->record('content.horoscope_saved', null, ['period' => $d['period'], 'sign' => $d['sign'], 'starts_on' => $d['starts_on']]);

        return back()->with('status', 'Saved as draft. A reviewer must approve it before it is published.');
    }

    public function decideHoroscope(Request $request, int $id): RedirectResponse
    {
        $this->authorize('content.publish');
        $h = DB::table('horoscopes')->find($id);
        abort_unless($h, 404);
        $a = $request->validate(['decision' => ['required', Rule::in(['publish', 'reject'])]])['decision'];
        if ($h->author_id === $request->user()->id) {
            throw ValidationException::withMessages(['decision' => 'A different person must review and publish what you wrote.']);
        }
        DB::table('horoscopes')->where('id', $id)->update(['status' => $a === 'publish' ? 'published' : 'rejected', 'reviewer_id' => $request->user()->id, 'published_at' => $a === 'publish' ? now() : null, 'updated_at' => now()]);
        $this->audit->record('content.horoscope_'.$a, null, ['id' => $id]);

        return back()->with('status', $a === 'publish' ? 'Published.' : 'Rejected.');
    }

    /** Creates the call with a chosen provider (in-app, Zoom, Google Meet) and tells the customer. */
    public static function switchProvider(object $b, string $provider, \App\Models\User $by): RedirectResponse
    {
        $m = app(\App\Domain\Meetings\Meetings::class);
        if ($b->status !== 'confirmed') {
            return back()->withErrors(['provider' => 'Only confirmed bookings can get a call link.']);
        }
        if (! $m->configured($provider)) {
            return back()->withErrors(['provider' => \App\Domain\Meetings\Meetings::PROVIDERS[$provider].' is not set up yet (Admin › Calling).']);
        }
        if (! $m->create($b, $provider)) {
            return back()->withErrors(['provider' => \Illuminate\Support\Facades\DB::table('bookings')->where('id', $b->id)->value('meeting_error') ?: 'Could not create the meeting.']);
        }
        $b = \Illuminate\Support\Facades\DB::table('bookings')->find($b->id);
        app(\App\Support\Audit\AuditLogger::class)->record('booking.meeting_created', null, ['booking' => $b->ref, 'provider' => $provider], $by);
        app(\App\Domain\Notify\Notifier::class)->send((int) $b->user_id, 'booking', 'Your consultation link', 'Join your consultation on '.\App\Domain\Booking\Slots::when($b).' here: '.$b->meeting_link, $b->ref, route('account.bookings'));

        return back()->with('status', \App\Domain\Meetings\Meetings::PROVIDERS[$provider].' link created and sent to the customer.');
    }
}
