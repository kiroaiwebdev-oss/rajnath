<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
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

    public const STATUSES = ['pending_payment' => 'Awaiting payment', 'paid' => 'Paid', 'fulfilling' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded'];

    protected function casts(): array
    {
        return ['policy_versions' => 'array', 'total_minor' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
