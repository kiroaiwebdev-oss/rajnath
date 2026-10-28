<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Messaging\DeliveryLog;
use App\Domain\Messaging\MailConfigurator;
use App\Domain\Messaging\ProviderConfig;
use App\Domain\Messaging\Sms\SmsManager;
use App\Http\Controllers\Controller;
use App\Mail\ProviderTestMail;
use App\Support\Audit\AuditLogger;
use App\Support\Net\SafeHost;
use App\Support\Settings\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/** Admin → Email & SMS. Owner-level permission (providers.manage) and step-up required to change or test. */
class ProvidersController extends Controller
{
    public function __construct(private ProviderConfig $cfg, private SettingsRepository $settings, private AuditLogger $audit) {}

    public function edit(): View
    {
        $this->authorize('providers.manage');
        $values = [];
        $masked = [];
        foreach ($this->allFieldKeys() as $key) {
            if ($this->cfg->isSecret($key)) {
                $masked[$key] = $this->settings->masked($key);
            } else {
                $values[$key] = $this->cfg->get($key);
            }
        }
        $values['sms.driver'] = $this->cfg->smsDriver();

        return view('admin.providers.edit', [
            'cfg' => ProviderConfig::class, 'values' => $values, 'masked' => $masked,
            'mailSource' => $this->cfg->mailConfigured() ? 'admin' : (config('mail.default') === 'log' ? 'log' : 'env'),
            'smsError' => ($d = app(SmsManager::class)->driverFor('+919000000000')) ? $d->configurationError() : null,
            'deliveries' => DB::table('provider_deliveries')->orderByDesc('id')->limit(20)->get(),
        ]);
    }

    public function updateMail(Request $request): RedirectResponse
    {
        $this->authorize('providers.manage');
        $data = $request->validate([
            'mail_host' => ['required', 'string', 'max:253'],
            'mail_port' => ['required', 'integer', Rule::in([25, 465, 587, 2525])],
            'mail_encryption' => ['required', Rule::in(['tls', 'ssl'])],
            'mail_username' => ['nullable', 'string', 'max:190'],
            'mail_password' => ['nullable', 'string', 'max:500'],
            'mail_from_address' => ['required', 'email', 'max:190'],
            'mail_from_name' => ['required', 'string', 'max:80'],
        ]);
        if ($err = SafeHost::check($data['mail_host'])) {
            throw ValidationException::withMessages(['mail_host' => $err]);
        }
        if ($data['mail_encryption'] === 'ssl' && (int) $data['mail_port'] !== 465) {
            throw ValidationException::withMessages(['mail_port' => 'SSL normally uses port 465. Use TLS for port 587.']);
        }
        $this->saveFields(ProviderConfig::MAIL_FIELDS, $data, $request->boolean('clear_mail_password') ? ['mail.password'] : []);

        return redirect()->route('admin.providers.edit')->with('status', 'Email settings saved. Send a test email to confirm they work.');
    }

    public function updateSms(Request $request): RedirectResponse
    {
        $this->authorize('providers.manage');
        $driver = $request->validate(['sms_driver' => ['required', Rule::in(array_keys(ProviderConfig::SMS_DRIVERS))]])['sms_driver'];
        $rules = [
            'sms_otp_text' => ['required', 'string', 'max:300', 'regex:/\{otp\}/'],
            'sms_international_driver' => ['required', Rule::in(['none', 'twilio'])],
            'sms_twilio_account_sid' => ['nullable', 'regex:/^AC[0-9a-fA-F]{32}$/'],
            'sms_twilio_auth_token' => ['nullable', 'string', 'max:200'],
            'sms_twilio_from' => ['nullable', 'regex:/^\+[1-9]\d{7,14}$/'],
            'sms_twilio_messaging_service_sid' => ['nullable', 'regex:/^MG[0-9a-fA-F]{32}$/'],
            'sms_fast2sms_api_key' => ['nullable', 'string', 'max:200'],
            'sms_fast2sms_route' => ['required', Rule::in(['dlt', 'otp'])],
            'sms_fast2sms_sender_id' => ['nullable', 'regex:/^[A-Za-z]{3,6}$/'],
            'sms_fast2sms_template_id' => ['nullable', 'regex:/^\d{1,30}$/'],
            'sms_fast2sms_variables' => ['nullable', 'string', 'max:200', 'regex:/\{otp\}/'],
        ];
        $data = $request->validate($rules, [
            'sms_otp_text.regex' => 'The message must contain {otp}.',
            'sms_fast2sms_variables.regex' => 'Template variables must contain {otp}.',
        ]);
        $fields = ProviderConfig::COMMON_SMS_FIELDS + ProviderConfig::SMS_FIELDS['twilio'] + ProviderConfig::SMS_FIELDS['fast2sms'];
        $clear = [];
        foreach (['sms.twilio.auth_token', 'sms.fast2sms.api_key'] as $s) {
            if ($request->boolean('clear_'.str_replace('.', '_', $s))) {
                $clear[] = $s;
            }
        }
        $this->saveFields($fields, $data, $clear);
        if ($this->settings->get('sms.driver') !== $driver) {
            $this->settings->set('sms.driver', $driver);
        }
        $err = ($d = app(SmsManager::class)->driverFor('+919000000000')) ? $d->configurationError() : null;

        return redirect()->route('admin.providers.edit')->with('status', $err
            ? 'SMS settings saved, but the selected provider is incomplete: '.$err
            : 'SMS settings saved. Send a test SMS to confirm they work.');
    }

    public function testMail(Request $request, MailConfigurator $mail, DeliveryLog $log): RedirectResponse
    {
        $this->authorize('providers.manage');
        $to = $request->validate(['test_email' => ['required', 'email', 'max:190']])['test_email'];
        $mail->apply();
        $started = hrtime(true);
        try {
            Mail::to($to)->send(new ProviderTestMail($request->user()->name));
            $log->record('email', 'smtp', 'test', $to, true, null, null, $started);
            $this->audit->record('providers.mail_test', null, ['result' => 'accepted']);

            return back()->with('status', 'Test email accepted by the mail server. Check the inbox (and spam folder) of '.$to.'.');
        } catch (Throwable $e) {
            $msg = $this->safeError($e->getMessage());
            $log->record('email', 'smtp', 'test', $to, false, null, $msg, $started);
            $this->audit->record('providers.mail_test', null, ['result' => 'failed']);

            return back()->withErrors(['test_email' => 'Sending failed: '.$msg]);
        }
    }

    public function testSms(Request $request, SmsManager $sms): RedirectResponse
    {
        $this->authorize('providers.manage');
        $raw = preg_replace('/[\s-]/', '', (string) $request->validate(['test_phone' => ['required', 'string', 'max:20']])['test_phone']);
        $e164 = preg_match('/^[6-9]\d{9}$/', $raw) ? '+91'.$raw : $raw;
        if (! preg_match('/^\+[1-9]\d{7,14}$/', $e164)) {
            throw ValidationException::withMessages(['test_phone' => 'Enter a 10-digit Indian mobile number or a number with country code, e.g. +9198…']);
        }
        $code = (string) random_int(100000, 999999);
        $r = $sms->sendOtp($e164, $code, 10, 'test');
        $this->audit->record('providers.sms_test', null, ['provider' => $r->provider, 'result' => $r->accepted ? 'accepted' : 'failed']);

        return $r->accepted
            ? back()->with('status', "Test SMS accepted by {$r->provider}. The code in the message should be {$code}.")
            : back()->withErrors(['test_phone' => 'Sending failed: '.$r->error]);
    }

    private function saveFields(array $fields, array $data, array $clear): void
    {
        foreach ($fields as $key => $f) {
            $input = $data[str_replace('.', '_', $key)] ?? null;
            if ($f['secret']) {
                if (in_array($key, $clear, true)) {
                    $this->settings->set($key, null, secret: true);
                } elseif ($input !== null && $input !== '') {
                    $this->settings->set($key, $input, secret: true); // blank = keep existing secret
                }

                continue;
            }
            if ($this->settings->get($key) !== $input) {
                $this->settings->set($key, $input);
            }
        }
    }

    private function allFieldKeys(): array
    {
        return array_keys(ProviderConfig::MAIL_FIELDS + ProviderConfig::COMMON_SMS_FIELDS + ProviderConfig::SMS_FIELDS['twilio'] + ProviderConfig::SMS_FIELDS['fast2sms']);
    }

    /** Provider error text without credentials. */
    private function safeError(string $m): string
    {
        foreach (['mail.password', 'mail.username'] as $k) {
            if ($v = $this->cfg->get($k)) {
                $m = str_replace((string) $v, '[hidden]', $m);
            }
        }

        return mb_substr($m, 0, 300);
    }
}
