<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nutrition education materials uploaded by RNDs and administrators. */
class Resource extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uploaded_by',
        'title',
        'description',
        'type',
        'file_path',
        'url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
