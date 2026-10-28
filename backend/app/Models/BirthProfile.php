<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BirthProfile extends Model
{
    public const LABELS = ['self' => 'Myself', 'spouse' => 'Spouse / partner', 'child' => 'Child', 'parent' => 'Parent', 'sibling' => 'Sibling', 'other' => 'Other'];

    public const ACCURACY = ['exact' => 'Exact (from birth certificate / records)', 'approximate' => 'Approximate (within about an hour)', 'unknown' => 'Unknown'];

    public const MAX_PER_USER = 20;

    protected $fillable = ['label', 'name', 'gender', 'birth_date', 'birth_time', 'time_accuracy', 'place_id', 'place_label', 'lat', 'lng', 'tz_name', 'is_primary'];

    protected $hidden = ['id', 'user_id'];

    protected static function booted(): void
    {
        static::creating(fn (BirthProfile $p) => $p->ref ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['birth_date' => 'date:Y-m-d', 'birth_utc' => 'datetime', 'is_primary' => 'boolean', 'lat' => 'float', 'lng' => 'float'];
    }

    public function getRouteKeyName(): string
    {
        return 'ref';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toApi(): array
    {
        return [
            'ref' => $this->ref, 'label' => $this->label, 'name' => $this->name, 'gender' => $this->gender,
            'birth_date' => $this->birth_date?->format('Y-m-d'), 'birth_time' => $this->birth_time ? substr($this->birth_time, 0, 5) : null,
            'time_accuracy' => $this->time_accuracy, 'place' => ['id' => $this->place_id, 'label' => $this->place_label, 'lat' => $this->lat, 'lng' => $this->lng, 'tz' => $this->tz_name],
            'birth_utc' => $this->birth_utc?->toIso8601ZuluString(), 'utc_offset_seconds' => $this->utc_offset_seconds,
            'tz_resolution' => $this->tz_resolution, 'is_primary' => $this->is_primary,
        ];
    }
}
