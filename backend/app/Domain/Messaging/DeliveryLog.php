<?php

namespace App\Domain\Messaging;

use App\Support\Logging\Redactor;
use Illuminate\Support\Facades\DB;

class DeliveryLog
{
    public function record(string $channel, string $provider, string $purpose, string $to, bool $accepted, ?string $messageId, ?string $error, ?int $startedNs = null): void
    {
        DB::table('provider_deliveries')->insert([
            'channel' => $channel,
            'provider' => $provider,
            'purpose' => $purpose,
            'to_masked' => Redactor::mask($to),
            'to_hash' => hash_hmac('sha256', strtolower($to), (string) config('app.key')),
            'status' => $accepted ? 'accepted' : 'failed',
            'provider_message_id' => $messageId ? mb_substr($messageId, 0, 120) : null,
            // Errors never include credentials; mask any address echoed back by a provider.
            'error' => $error ? mb_substr((string) Redactor::redact($error), 0, 500) : null,
            'duration_ms' => $startedNs ? (int) ((hrtime(true) - $startedNs) / 1e6) : null,
            'created_at' => now(),
        ]);
    }
}
