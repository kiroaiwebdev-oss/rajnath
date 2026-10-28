<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Refund extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->ref ??= (string) Str::ulid());
    }

    public function getRouteKeyName(): string
    {
        return 'ref';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public const STATUSES = ['requested' => 'Awaiting approval', 'rejected' => 'Rejected', 'processing' => 'Processing', 'processed' => 'Refunded', 'failed' => 'Failed'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'decided_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
