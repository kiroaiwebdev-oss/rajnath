<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OfferingVariant extends Model
{
    public const MODES = ['chat' => 'Chat', 'audio' => 'Audio call', 'video' => 'Video call', 'in_person' => 'In person (Patna office)', 'written' => 'Written answer', 'report' => 'Report', 'onsite' => 'On-site visit', 'puja' => 'Puja'];

    protected $fillable = ['name', 'mode', 'duration_min', 'participants', 'urgent', 'price_minor', 'currency', 'active', 'sort'];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    protected static function booted(): void
    {
        static::creating(fn (OfferingVariant $v) => $v->ref ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['urgent' => 'boolean', 'active' => 'boolean', 'price_minor' => 'integer', 'translations' => 'array'];
    }

    /** Name in the current language (falls back to English). */
    public function label(?string $locale = null): string
    {
        return $this->translations[$locale ?? app()->getLocale()]['name'] ?? $this->name;
    }
}
