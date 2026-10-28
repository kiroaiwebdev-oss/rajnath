<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceFamily extends Model
{
    protected $fillable = ['code', 'name', 'slug', 'summary', 'requires_supplier', 'sort'];

    protected function casts(): array
    {
        return ['requires_supplier' => 'boolean'];
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
