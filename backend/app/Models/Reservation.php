<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Reservation extends Model
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

    protected function casts(): array
    {
        return ['cash_minor' => 'integer', 'bonus_minor' => 'integer', 'consumed_minor' => 'integer'];
    }

    public function heldMinor(): int
    {
        return in_array($this->status, ['held', 'partial'], true) ? $this->cash_minor + $this->bonus_minor - $this->consumed_minor : 0;
    }
}
