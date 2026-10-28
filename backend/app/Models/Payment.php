<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Payment extends Model
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

    public const STATUSES = ['created' => 'Awaiting payment', 'pending' => 'Processing', 'captured' => 'Paid', 'failed' => 'Failed', 'cancelled' => 'Cancelled', 'ambiguous' => 'Being checked'];

    public const FINAL = ['captured', 'failed', 'cancelled'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'amount_minor' => 'integer', 'bonus_minor' => 'integer', 'refunded_minor' => 'integer', 'captured_at' => 'datetime', 'last_checked_at' => 'datetime'];
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL, true);
    }

    /** Captured amount not yet refunded or committed to a pending refund. */
    public function refundableMinor(): int
    {
        if ($this->status !== 'captured') {
            return 0;
        }
        $committed = $this->refunds()->whereIn('status', ['requested', 'processing', 'processed'])->sum('amount_minor');

        return max(0, $this->amount_minor - (int) $committed);
    }

    public function label(): string
    {
        return $this->purpose === 'wallet_recharge' ? 'Wallet recharge' : 'Order payment';
    }
}
