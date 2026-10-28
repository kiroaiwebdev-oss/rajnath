<?php

namespace App\Domain\Payments;

use Illuminate\Support\Facades\DB;

/** Records reconciliation problems for the finance team. Deduplicated by key. */
class Findings
{
    public static function raise(string $kind, string $severity, ?object $subject, array $details, ?string $dedupe = null): void
    {
        $ref = $subject ? (string) ($subject->ref ?? $subject->id) : null;
        DB::table('reconciliation_findings')->insertOrIgnore([
            'kind' => $kind, 'severity' => $severity,
            'subject_type' => $subject ? class_basename($subject) : null, 'subject_ref' => $ref,
            'details' => json_encode($details, JSON_UNESCAPED_SLASHES),
            'dedupe_key' => $dedupe ?? $kind.':'.($ref ?? sha1(json_encode($details))),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
