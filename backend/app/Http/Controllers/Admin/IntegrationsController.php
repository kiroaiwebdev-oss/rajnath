<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Assistant\AiProviders;
use App\Domain\Assistant\Assistant;
use App\Domain\Notify\Fcm;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLogger;
use App\Support\Settings\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/** Admin › Push notifications (Firebase) and Admin › AI assistant (providers, knowledge, conversations). */
class IntegrationsController extends Controller
{
    public function __construct(private SettingsRepository $settings, private AuditLogger $audit) {}

    // ---------------- Push (Firebase Cloud Messaging) ----------------
    public function push(Fcm $fcm): View
    {
        $this->authorize('providers.manage');

        return view('admin.integrations.push', [
            'account' => $fcm->account(), 'client' => $fcm->clientConfig(),
            'values' => ['api_key' => $this->settings->get('push.android.api_key'), 'app_id' => $this->settings->get('push.android.app_id'), 'sender_id' => $this->settings->get('push.android.sender_id')],
            'devices' => DB::table('push_tokens')->count(),
            'recent' => DB::table('user_notifications')->orderByDesc('id')->limit(20)->get(['kind', 'title', 'channels', 'created_at']),
        ]);
    }

    public function savePush(Request $request): RedirectResponse
    {
        $this->authorize('providers.manage');
        $d = $request->validate([
            'service_account' => ['nullable', 'string', 'max:10000'],
            'api_key' => ['required', 'string', 'regex:/^AIza[0-9A-Za-z_\-]{35}$/'],
            'app_id' => ['required', 'string', 'regex:/^1:\d{6,20}:android:[0-9a-f]{6,40}$/'],
            'sender_id' => ['required', 'string', 'regex:/^\d{6,20}$/'],
        ], ['api_key.regex' => 'The Android API key starts with AIza (Project settings › General › Your apps › Android).',
            'app_id.regex' => 'The Android App ID looks like 1:1234567890:android:abc123…',
            'sender_id.regex' => 'The sender ID is the project number (digits only).']);
        if (! empty($d['service_account'])) {
            if ($err = Fcm::validateServiceAccount($d['service_account'])) {
                throw ValidationException::withMessages(['service_account' => $err]);
            }
            $this->settings->set('push.fcm.service_account', $d['service_account'], secret: true, group: 'push');
        } elseif (! app(Fcm::class)->configured()) {
            throw ValidationException::withMessages(['service_account' => 'Paste the service-account JSON (Project settings › Service accounts › Generate new private key).']);
        }
        $this->settings->set('push.android.api_key', $d['api_key'], group: 'push');
        $this->settings->set('push.android.app_id', $d['app_id'], group: 'push');
        $this->settings->set('push.android.sender_id', $d['sender_id'], group: 'push');
        $this->audit->record('providers.push_saved', null, ['service_account_replaced' => ! empty($d['service_account'])]);

        return back()->with('status', 'Firebase settings saved. Use “Check credentials”, then open the app once so it registers for notifications.');
    }

    public function testPush(Request $request, Fcm $fcm): RedirectResponse
    {
        $this->authorize('providers.manage');
        $token = $request->validate(['token' => ['nullable', 'string', 'max:512']])['token'] ?? null;
        try {
            $fcm->accessToken(fresh: true);
            $msg = 'Credentials accepted by Google.';
            if ($token) {
                $r = $fcm->send($token, 'Test', 'Test notification', [], validateOnly: true);
                $msg .= $r === 'sent' ? ' The device token is valid.' : ' Device token check: '.$r;
            }
            $this->audit->record('providers.push_test', null, ['result' => 'ok']);

            return back()->with('status', $msg);
        } catch (Throwable $e) {
            $this->audit->record('providers.push_test', null, ['result' => 'failed']);

            return back()->withErrors(['token' => mb_substr($e->getMessage(), 0, 300)]);
        }
    }

    // ---------------- AI assistant ----------------
    public function assistant(AiProviders $ai): View
    {
        $this->authorize('assistant.knowledge');
        $providers = [];
        foreach (AiProviders::PROVIDERS as $k => $p) {
            $providers[$k] = $p + ['enabled' => (bool) $this->settings->get("ai.$k.enabled"), 'current_model' => $ai->model($k), 'configured' => $ai->configured($k), 'key_masked' => $this->settings->masked("ai.$k.key")];
        }

        return view('admin.integrations.assistant', [
            'providers' => $providers, 'enabled' => (bool) $this->settings->get('ai.enabled'), 'order' => (string) $this->settings->get('ai.order', 'groq,gemini,cloudflare,openai'),
            'cfAccount' => $this->settings->get('ai.cloudflare.account_id'), 'limits' => ['user' => Assistant::dailyLimit(auth()->user()), 'guest' => Assistant::dailyLimit(null)],
            'active' => $ai->order(), 'canConfigure' => auth()->user()->can('providers.manage'),
            'knowledge' => DB::table('assistant_knowledge')->orderByDesc('active')->orderBy('title')->get(),
            'conversations' => DB::table('assistant_conversations as c')->leftJoin('users as u', 'u.id', '=', 'c.user_id')
                ->select('c.*', 'u.name as customer')->selectSub(DB::table('assistant_messages')->whereColumn('conversation_id', 'c.id')->selectRaw('count(*)'), 'messages')
                ->orderByDesc('c.handoff')->orderByDesc('c.updated_at')->limit(30)->get(),
        ]);
    }

    public function saveAssistant(Request $request): RedirectResponse
    {
        $this->authorize('providers.manage');
        $rules = ['enabled' => ['nullable', 'boolean'], 'order' => ['required', 'array', 'min:1'], 'order.*' => [Rule::in(array_keys(AiProviders::PROVIDERS))],
            'daily_user' => ['required', 'integer', 'between:1,500'], 'daily_guest' => ['required', 'integer', 'between:0,100'],
            'cloudflare_account_id' => ['nullable', 'regex:/^[a-f0-9]{32}$/']];
        foreach (array_keys(AiProviders::PROVIDERS) as $p) {
            $rules["{$p}_enabled"] = ['nullable', 'boolean'];
            $rules["{$p}_key"] = ['nullable', 'string', 'max:300'];
            $rules["{$p}_model"] = ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9@\/._:\-]+$/'];
        }
        $d = $request->validate($rules, ['cloudflare_account_id.regex' => 'The Cloudflare account ID is 32 hexadecimal characters.']);
        foreach (array_keys(AiProviders::PROVIDERS) as $p) {
            $this->settings->set("ai.$p.enabled", (bool) ($d["{$p}_enabled"] ?? false), group: 'ai');
            if (! empty($d["{$p}_key"])) {
                $this->settings->set("ai.$p.key", trim($d["{$p}_key"]), secret: true, group: 'ai');
            }
            $this->settings->set("ai.$p.model", $d["{$p}_model"] ?? '', group: 'ai');
        }
        $this->settings->set('ai.cloudflare.account_id', $d['cloudflare_account_id'] ?? '', group: 'ai');
        $this->settings->set('ai.order', implode(',', array_unique($d['order'])), group: 'ai');
        $this->settings->set('ai.daily_user', (int) $d['daily_user'], group: 'ai');
        $this->settings->set('ai.daily_guest', (int) $d['daily_guest'], group: 'ai');
        $this->settings->set('ai.enabled', (bool) ($d['enabled'] ?? false), group: 'ai');
        $this->audit->record('providers.ai_saved', null, ['enabled' => (bool) ($d['enabled'] ?? false), 'order' => $d['order']]);
        $active = app(AiProviders::class)->order();

        return back()->with('status', ($d['enabled'] ?? false) && ! $active ? 'Saved, but no provider is both enabled and complete (key + model), so the assistant stays off.' : 'AI settings saved. Test each provider below.');
    }

    public function testAssistant(Request $request, AiProviders $ai): RedirectResponse
    {
        $this->authorize('providers.manage');
        $p = $request->validate(['provider' => ['required', Rule::in(array_keys(AiProviders::PROVIDERS))]])['provider'];
        if (! $ai->configured($p)) {
            return back()->withErrors(['provider' => AiProviders::PROVIDERS[$p]['label'].' needs an API key and a model first.']);
        }
        try {
            [$text, , , $ms] = $ai->call($p, [['role' => 'user', 'content' => 'Reply with the single word OK.']], 20);
            $this->audit->record('providers.ai_test', null, ['provider' => $p, 'result' => 'ok']);

            return back()->with('status', AiProviders::PROVIDERS[$p]['label']." answered in {$ms} ms: “".mb_substr($text, 0, 40).'”');
        } catch (Throwable $e) {
            $this->audit->record('providers.ai_test', null, ['provider' => $p, 'result' => 'failed']);

            return back()->withErrors(['provider' => mb_substr($e->getMessage(), 0, 300)]);
        }
    }

    public function saveKnowledge(Request $request): RedirectResponse
    {
        $this->authorize('assistant.knowledge');
        $d = $request->validate(['id' => ['nullable', 'integer', 'exists:assistant_knowledge,id'], 'title' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:4000'], 'active' => ['nullable', 'boolean']]);
        $row = ['title' => $d['title'], 'body' => $d['body'], 'active' => (bool) ($d['active'] ?? false), 'updated_by' => $request->user()->id, 'updated_at' => now()];
        empty($d['id']) ? DB::table('assistant_knowledge')->insert($row + ['created_at' => now()]) : DB::table('assistant_knowledge')->where('id', $d['id'])->update($row);
        $this->audit->record('assistant.knowledge_saved', null, ['title' => $d['title']]);

        return back()->with('status', 'Knowledge note saved. The assistant uses it from the next message.');
    }

    public function conversation(string $ref): View
    {
        $this->authorize('assistant.knowledge');
        $c = DB::table('assistant_conversations')->where('ref', $ref)->first();
        abort_unless($c, 404);
        $this->audit->record('assistant.conversation_viewed', null, ['conversation' => $ref]);

        return view('admin.integrations.conversation', ['c' => $c, 'customer' => $c->user_id ? DB::table('users')->find($c->user_id) : null,
            'messages' => DB::table('assistant_messages')->where('conversation_id', $c->id)->orderBy('id')->get()]);
    }
}
