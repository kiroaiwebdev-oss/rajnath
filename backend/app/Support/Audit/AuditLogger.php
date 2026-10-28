<?php

namespace App\Support\Audit;

use App\Support\Logging\Redactor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Appends tamper-evident audit records. Each row's hash covers its content plus the previous
 * row's hash; appends are serialised with a row lock on audit_chain_heads so concurrent writers
 * cannot fork the chain. `verify()` recomputes the chain.
 */
class AuditLogger
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    public function record(string $action, ?object $subject = null, array $context = [], ?object $actor = null, ?string $actorLabel = null): void
    {
        $actor ??= auth()->user();
        $request = app()->runningInConsole() ? null : request();

        $row = [
            'actor_id' => $actor?->id,
            'actor_label' => $actorLabel ?? ($actor ? 'user:'.$actor->ref : (app()->runningInConsole() ? 'cli' : 'guest')),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_ref' => $subject ? (string) ($subject->ref ?? $subject->getKey()) : null,
            'context' => $context ? json_encode(Redactor::redact($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'ip' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            'created_at' => now('UTC')->format('Y-m-d H:i:s.u'),
        ];

        // The chain head row is created by migration; locking only that row serialises appends
        // without gap-lock deadlocks. Deadlock/serialization failures are retried.
        DB::transaction(function () use ($row) {
            $head = DB::table('audit_chain_heads')->where('id', 1)->lockForUpdate()->first();
            if (! $head) {
                throw new \RuntimeException('Audit chain head missing; run migrations.');
            }
            $row['prev_hash'] = $head->last_hash;
            $row['hash'] = self::hashRow($row);
            $id = DB::table('audit_logs')->insertGetId($row);
            DB::table('audit_chain_heads')->where('id', 1)->update(['last_hash' => $row['hash'], 'last_id' => $id]);
        }, attempts: 5);
    }

    public static function hashRow(array $row): string
    {
        $fields = ['prev_hash', 'actor_id', 'actor_label', 'action', 'subject_type', 'subject_ref', 'context', 'ip', 'created_at'];
        $material = implode("\x1f", array_map(fn ($f) => (string) ($row[$f] ?? ''), $fields));

        return hash('sha256', $material);
    }

    /** @return array{ok: bool, checked: int, broken_at: ?int} */
    public function verify(): array
    {
        $prev = self::GENESIS;
        $checked = 0;
        foreach (DB::table('audit_logs')->orderBy('id')->cursor() as $r) {
            $row = (array) $r;
            $created = Carbon::parse($row['created_at'])->format('Y-m-d H:i:s.u');
            $row['created_at'] = $created;
            if ($row['prev_hash'] !== $prev || self::hashRow($row) !== $row['hash']) {
                return ['ok' => false, 'checked' => $checked, 'broken_at' => $row['id']];
            }
            $prev = $row['hash'];
            $checked++;
        }

        return ['ok' => true, 'checked' => $checked, 'broken_at' => null];
    }
}
