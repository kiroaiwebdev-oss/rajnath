<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Enquiry extends Model
{
    public const STATUSES = ['new' => 'New', 'contacted' => 'Contacted', 'closed' => 'Closed', 'spam' => 'Spam'];

    protected $fillable = ['user_id', 'offering_id', 'name', 'phone_e164', 'email', 'mode', 'language', 'preferred_date', 'birth_date', 'birth_time', 'birth_place', 'message', 'ip'];

    protected static function booted(): void
    {
        static::creating(fn (Enquiry $e) => $e->ref ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['preferred_date' => 'date', 'birth_date' => 'date'];
    }

    public function getRouteKeyName(): string
    {
        return 'ref';
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }
}
