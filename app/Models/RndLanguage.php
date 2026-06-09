<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Structured junction table for RND language capabilities.
 * Supports the matching engine filtering by language.
 */
class RndLanguage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'rnd_id',
        'language_code',
        'language_name',
    ];

    public function rnd(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rnd_id');
    }
}
