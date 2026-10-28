<?php

namespace App\Domain\Messaging\Sms;

use App\Domain\Messaging\ProviderConfig;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fast2SMS bulkV2 API (Indian numbers only).
 *  - route=dlt: your DLT sender ID + DLT message ID; variables_values fill {#var#} in order.
 *  - route=otp: Fast2SMS standard OTP message; variables_values is the code.
 * Docs: https://docs.fast2sms.com/reference/dlt-sms
 */
class Fast2SmsDriver implements SmsDriver
{
    public const ENDPOINT = 'https://www.fast2sms.com/dev/bulkV2';

    public function __construct(private ProviderConfig $cfg) {}

    public function name(): string
    {
        return 'fast2sms';
    }

    public function configurationError(): ?string
    {
        if (! $this->cfg->get('sms.fast2sms.api_key')) {
            return 'Fast2SMS API key is missing.';
        }
        if ($this->cfg->get('sms.fast2sms.route') === 'dlt') {
            if (! preg_match('/^[A-Za-z]{3,6}$/', (string) $this->cfg->get('sms.fast2sms.sender_id'))) {
                return 'Fast2SMS DLT sender ID must be 3–6 letters.';
            }
            if (! preg_match('/^\d{1,30}$/', (string) $this->cfg->get('sms.fast2sms.template_id'))) {
                return 'Fast2SMS DLT message ID is missing or not numeric.';
            }
        }

        return null;
    }

    public function sendOtp(string $e164, string $otp, int $minutes): SmsResult
    {
        if ($err = $this->configurationError()) {
            return SmsResult::failed('fast2sms', $err);
        }
        if (! preg_match('/^\+91([6-9]\d{9})$/', $e164, $m)) {
            return SmsResult::failed('fast2sms', 'Fast2SMS can only send to Indian mobile numbers.');
        }
        $route = $this->cfg->get('sms.fast2sms.route');
        $query = ['route' => $route, 'numbers' => $m[1], 'flash' => 0];
        if ($route === 'dlt') {
            $query['sender_id'] = strtoupper($this->cfg->get('sms.fast2sms.sender_id'));
            $query['message'] = $this->cfg->get('sms.fast2sms.template_id');
            $query['variables_values'] = strtr((string) $this->cfg->get('sms.fast2sms.variables'), ['{otp}' => $otp, '{minutes}' => (string) $minutes]);
        } else {
            $query['variables_values'] = $otp;
        }

        try {
            // API key travels in the authorization header, never in the URL (keeps it out of logs).
            $res = Http::timeout(10)->connectTimeout(5)
                ->withHeaders(['authorization' => $this->cfg->get('sms.fast2sms.api_key'), 'cache-control' => 'no-cache'])
                ->get(self::ENDPOINT, $query);
        } catch (Throwable $e) {
            return SmsResult::failed('fast2sms', 'Could not reach Fast2SMS: '.class_basename($e));
        }
        $json = $res->json() ?? [];
        if (($json['return'] ?? false) === true) {
            return new SmsResult(true, 'fast2sms', (string) ($json['request_id'] ?? ''));
        }
        $msg = $json['message'] ?? 'unexpected response';

        return SmsResult::failed('fast2sms', 'Fast2SMS error '.($json['status_code'] ?? $res->status()).': '.(is_array($msg) ? implode(' ', $msg) : $msg));
    }
}
