<?php

namespace App\Domain\Places;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Prefix search over GeoNames cities (pop. ≥ 15,000). Data © GeoNames, CC BY 4.0. */
class PlaceSearch
{
    public function search(string $q, int $limit = 10): array
    {
        $q = trim(preg_replace('/\s+/', ' ', $q));
        if (mb_strlen($q) < 2) {
            return [];
        }
        $name = $q;
        $hint = null;
        if (str_contains($q, ',')) {
            [$name, $hint] = array_map('trim', explode(',', $q, 2));
        }
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], Str::ascii($name)).'%';
        $rows = DB::table('places')->where('ascii_name', 'like', $like)
            ->when($hint, fn ($w) => $w->where(fn ($x) => $x->where('admin1', 'like', $hint.'%')->orWhere('country', 'like', $hint.'%')))
            ->orderByRaw("country_code = 'IN' desc")->orderByDesc('population')->limit($limit)->get();

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'label' => implode(', ', array_filter([$r->name, $r->admin1, $r->country])),
            'lat' => (float) $r->lat, 'lng' => (float) $r->lng, 'tz' => $r->tz_name,
        ])->all();
    }
}
