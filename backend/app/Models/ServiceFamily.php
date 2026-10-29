<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceFamily extends Model
{
    protected $fillable = ['code', 'name', 'slug', 'summary', 'requires_supplier', 'sort'];

    protected function casts(): array
    {
        return ['requires_supplier' => 'boolean', 'translations' => 'array'];
    }

    /** Name in the current language (falls back to English). */
    public function label(?string $locale = null): string
    {
        return $this->translations[$locale ?? app()->getLocale()]['name'] ?? $this->name;
    }

    public function blurb(?string $locale = null): ?string
    {
        return $this->translations[$locale ?? app()->getLocale()]['summary'] ?? $this->summary;
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(Offering::class, 'family_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
