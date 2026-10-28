<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Meetings\LiveKit;
use App\Domain\Meetings\Meetings;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLogger;
use App\Support\Settings\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Admin › Calling: default provider, LiveKit server, Zoom app and Google Meet connection. */
class CallingController extends Controller
{
    public function __construct(private SettingsRepository $s, private Meetings $m, private AuditLogger $audit) {}

    public function edit(): View
    {
        $this->authorize('providers.manage');

        return view('admin.integrations.calling', [
            'default' => $this->m->defaultProvider(), 'ready' => collect(Meetings::PROVIDERS)->keys()->mapWithKeys(fn ($p) => [$p => $this->m->configured($p)])->all(),
            'lk' => ['url' => $this->s->get('calling.livekit.url'), 'key' => $this->s->get('calling.livekit.key'), 'secret' => $this->s->masked('calling.livekit.secret')],
            'zoom' => ['account_id' => $this->s->get('calling.zoom.account_id'), 'client_id' => $this->s->get('calling.zoom.client_id'), 'secret' => $this->s->masked('calling.zoom.client_secret')],
            'google' => ['client_id' => $this->s->get('calling.google.client_id'), 'secret' => $this->s->masked('calling.google.client_secret'), 'connected' => (bool) $this->s->get('calling.google.refresh_token'),
                'calendar' => $this->s->get('calling.google.calendar') ?: 'primary', 'redirect' => route('admin.calling.google.callback')],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('providers.manage');
        $d = $request->validate([
            'default' => ['required', Rule::in(array_keys(Meetings::PROVIDERS))],
            'livekit_url' => ['nullable', 'regex:#^wss://[a-z0-9.\-]+(:\d+)?/?$#i'], 'livekit_key' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-]+$/'], 'livekit_secret' => ['nullable', 'string', 'min:20', 'max:200'],
            'zoom_account_id' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-]+$/'], 'zoom_client_id' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-]+$/'], 'zoom_client_secret' => ['nullable', 'string', 'max:200'],
            'google_client_id' => ['nullable', 'string', 'max:200', 'regex:/\.apps\.googleusercontent\.com$/'], 'google_client_secret' => ['nullable', 'string', 'max:200'],
            'google_calendar' => ['nullable', 'string', 'max:200'],
        ], ['livekit_url.regex' => 'Use the wss:// address of your LiveKit server, e.g. wss://rtc.example.com', 'google_client_id.regex' => 'The Google client ID ends with .apps.googleusercontent.com']);
        foreach (['livekit_url' => 'calling.livekit.url', 'livekit_key' => 'calling.livekit.key', 'zoom_account_id' => 'calling.zoom.account_id', 'zoom_client_id' => 'calling.zoom.client_id',
            'google_client_id' => 'calling.google.client_id', 'google_calendar' => 'calling.google.calendar'] as $f => $k) {
            $this->s->set($k, isset($d[$f]) ? rtrim(trim($d[$f]), '/') : '', group: 'calling');
        }
        foreach (['livekit_secret' => 'calling.livekit.secret', 'zoom_client_secret' => 'calling.zoom.client_secret', 'google_client_secret' => 'calling.google.client_secret'] as $f => $k) {
            if (! empty($d[$f])) {
                $this->s->set($k, trim($d[$f]), secret: true, group: 'calling');
            }
        }
        $this->s->set('calling.default', $d['default'], group: 'calling');
        $this->audit->record('providers.calling_saved', null, ['default' => $d['default']]);
        $warn = $this->m->configured($d['default']) ? '' : ' The default provider is not complete yet, so new bookings get an in-app call link if that is set up, otherwise you add a link by hand.';

        return back()->with('status', 'Calling settings saved.'.$warn);
    }

    public function test(Request $request, LiveKit $lk): RedirectResponse
    {
        $this->authorize('providers.manage');
        $p = $request->validate(['provider' => ['required', Rule::in(['livekit', 'zoom', 'google_meet'])]])['provider'];
        if (! $this->m->configured($p)) {
            return back()->withErrors(['provider' => Meetings::PROVIDERS[$p].' is not complete yet.']);
        }
        try {
            if ($p === 'livekit') {
                // Lists rooms with a server token: proves the URL, key and secret.
                $http = 'https://'.preg_replace('#^wss://#', '', rtrim((string) $lk->url(), '/'));
                $t = $lk->jwt('admin-test', 'Admin test', '', []);
                $claims = json_decode(base64_decode(strtr(explode('.', $t)[1], '-_', '+/')), true);
                $claims['video'] = ['roomList' => true];
                $b64 = fn ($x) => rtrim(strtr(base64_encode($x), '+/', '-_'), '=');
                $unsigned = $b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])).'.'.$b64(json_encode($claims));
                $tok = $unsigned.'.'.$b64(hash_hmac('sha256', $unsigned, (string) $this->s->get('calling.livekit.secret'), true));
                $res = Http::withToken($tok)->timeout(10)->post($http.'/twirp/livekit.RoomService/ListRooms', (object) []);
                abort_unless($res->successful(), 422, 'LiveKit answered '.$res->status().'. Check the URL, API key and secret.');
                $msg = 'LiveKit server reachable; key and secret accepted.';
            } elseif ($p === 'zoom') {
                $this->m->zoomToken();
                $msg = 'Zoom accepted the app credentials.';
            } else {
                $res = Http::withToken($this->m->googleToken())->timeout(10)->get('https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode((string) ($this->s->get('calling.google.calendar') ?: 'primary')).'/events', ['maxResults' => 1]);
                abort_unless($res->successful(), 422, 'Google Calendar answered '.$res->status().'.');
                $msg = 'Google Calendar connected.';
            }
            $this->audit->record('providers.calling_test', null, ['provider' => $p, 'result' => 'ok']);

            return back()->with('status', $msg);
        } catch (Throwable $e) {
            $this->audit->record('providers.calling_test', null, ['provider' => $p, 'result' => 'failed']);

            return back()->withErrors(['provider' => mb_substr($e->getMessage(), 0, 300)]);
        }
    }

    public function googleConnect(Request $request): RedirectResponse
    {
        $this->authorize('providers.manage');
        $id = $this->s->get('calling.google.client_id');
        if (! $id || ! $this->s->get('calling.google.client_secret')) {
            return back()->withErrors(['provider' => 'Save the Google client ID and secret first.']);
        }
        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        // A link (not a redirect) because the CSP form-action rule blocks form posts that redirect off-site.
        return back()->with('google_url', Meetings::googleAuthUrl($id, $state));
    }

    public function googleCallback(Request $request): RedirectResponse
    {
        $this->authorize('providers.manage');
        $state = $request->session()->pull('google_oauth_state');
        if (! $state || ! hash_equals($state, (string) $request->query('state')) || ! $request->query('code')) {
            return redirect()->route('admin.calling.edit')->withErrors(['provider' => 'Google connection was cancelled or expired. Please try again.']);
        }
        try {
            $this->m->googleExchange((string) $request->query('code'));
        } catch (Throwable $e) {
            return redirect()->route('admin.calling.edit')->withErrors(['provider' => mb_substr($e->getMessage(), 0, 300)]);
        }
        $this->audit->record('providers.google_connected', null, []);

        return redirect()->route('admin.calling.edit')->with('status', 'Google account connected. New bookings can now get Google Meet links.');
    }
}
