<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Offering extends Model
{
    public const STATUSES = ['draft' => 'Draft (hidden)', 'active' => 'Active (public)', 'paused' => 'Paused (hidden)', 'retired' => 'Retired'];

    protected $fillable = ['family_id', 'slug', 'status', 'featured', 'image_path', 'sort'];

    protected static function booted(): void
    {
        static::creating(fn (Offering $o) => $o->ref ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['featured' => 'boolean', 'activated_at' => 'datetime'];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(ServiceFamily::class, 'family_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(OfferingTranslation::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(OfferingVariant::class)->orderBy('sort')->orderBy('id');
    }

    public function bundleItems(): BelongsToMany
    {
        return $this->belongsToMany(Offering::class, 'bundle_items', 'bundle_offering_id', 'item_offering_id');
    }

    public function scopePublic(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    public function t(?string $locale = null): ?OfferingTranslation
    {
        $locale ??= app()->getLocale();
        $all = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();

        $t = $all->firstWhere('locale', $locale);

        // A translation is used only once it has a title; otherwise English is shown.
        return $t && $t->title ? $t : $all->firstWhere('locale', 'en');
    }

    public function fromPriceMinor(): ?int
    {
        $prices = $this->variants->where('active', true)->pluck('price_minor')->filter(fn ($p) => $p !== null);

        return $prices->isEmpty() ? null : (int) $prices->min();
    }

    public function toApi(bool $detail = false): array
    {
        $t = $this->t();
        $out = [
            'ref' => $this->ref, 'slug' => $this->slug, 'family' => $this->family?->code, 'family_name' => $this->family?->label(),
            'title' => $t?->title, 'summary' => $t?->summary, 'featured' => $this->featured,
            'image_url' => $this->image_path ? asset($this->image_path) : null,
            'from_price_minor' => $this->fromPriceMinor(), 'currency' => 'INR',
        ];
        if ($detail) {
            $out += [
                'body' => $t?->body, 'includes' => $t?->includes ?? [], 'audience' => $t?->audience ?? [],
                'exclusions' => $t?->exclusions, 'disclaimer' => $t?->disclaimer,
                'variants' => $this->variants->where('active', true)->values()->map(fn ($v) => [
                    'ref' => $v->ref, 'name' => $v->label(), 'mode' => $v->mode, 'duration_min' => $v->duration_min,
                    'participants' => $v->participants, 'urgent' => $v->urgent, 'price_minor' => $v->price_minor, 'currency' => $v->currency,
                ])->all(),
            ];
        }

        return $out;
    }
}
