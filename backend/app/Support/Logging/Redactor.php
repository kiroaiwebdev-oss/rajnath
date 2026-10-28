<?php

namespace App\Support\Logging;

/**
 * Removes secrets and masks personal data before it reaches logs or the audit trail.
 */
class Redactor
{
    private const SECRET_KEYS = '/(pass(word)?|secret|token|api[_-]?key|otp|code|authorization|cookie|signature|private|mfa|recovery)/i';
    private const PII_KEYS = '/(email|phone|mobile|dob|birth|address|gotra|lat|lng|ip)/i';

    public static function redact(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::redact($v, is_string($k) ? $k : $key);
            }

            return $out;
        }
        if ($key !== null && preg_match(self::SECRET_KEYS, $key)) {
            return '[REDACTED]';
        }
        if (is_string($value)) {
            if ($key !== null && preg_match(self::PII_KEYS, $key)) {
                return self::mask($value);
            }
            // Mask free-text emails and Indian/E.164 phone numbers wherever they appear.
            $value = preg_replace_callback('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', fn ($m) => self::mask($m[0]), $value);
            $value = preg_replace_callback('/\+?\d[\d\s-]{8,14}\d/', fn ($m) => self::mask($m[0]), $value);
        }

        return $value;
    }

    public static function mask(string $v): string
    {
        $len = mb_strlen($v);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return mb_substr($v, 0, 2).str_repeat('*', max(3, $len - 4)).mb_substr($v, -2);
    }
}
