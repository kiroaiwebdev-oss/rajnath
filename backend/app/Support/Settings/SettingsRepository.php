<?php

namespace App\Support\Settings;

use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Owner-editable settings. Secret values are encrypted at rest with the application key,
 * never returned to views in clear text (use masked()), and every change is audited without
 * the value itself.
 */
class SettingsRepository
{
    private const CACHE_KEY = 'arj.settings.v1';

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function set(string $key, mixed $value, bool $secret = false, ?string $group = null): void
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stored = $secret ? Crypt::encryptString($encoded) : $encoded;
        $existing = DB::table('settings')->where('key', $key)->first();

        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            [
                'group' => $group ?? strtok($key, '.'),
                'value' => $stored,
                'is_secret' => $secret,
                'updated_by' => auth()->id(),
                'updated_at' => now(),
                'created_at' => $existing->created_at ?? now(),
            ]
        );
        Cache::forget(self::CACHE_KEY);

        app(AuditLogger::class)->record('settings.updated', null, [
            'key' => $key,
            'secret' => $secret,
            'value' => $secret ? '[REDACTED]' : $value,
            'previous' => $secret || ! $existing ? null : json_decode($existing->value, true),
        ]);
    }

    public function masked(string $key): ?string
    {
        $v = $this->get($key);
        if ($v === null || $v === '') {
            return null;
        }
        $s = (string) (is_scalar($v) ? $v : json_encode($v));

        return strlen($s) <= 4 ? '••••' : '••••••'.substr($s, -4);
    }

    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            $out = [];
            foreach (DB::table('settings')->get() as $row) {
                $raw = $row->is_secret ? Crypt::decryptString($row->value) : $row->value;
                $out[$row->key] = json_decode($raw, true);
            }

            return $out;
        });
    }
}
