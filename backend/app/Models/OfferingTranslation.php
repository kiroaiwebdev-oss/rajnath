<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfferingTranslation extends Model
{
    public $timestamps = false;

    protected $fillable = ['locale', 'title', 'summary', 'body', 'includes', 'audience', 'exclusions', 'disclaimer', 'meta_description'];

    protected function casts(): array
    {
        return ['includes' => 'array', 'audience' => 'array'];
    }
}
