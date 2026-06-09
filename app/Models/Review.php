<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Client reviews of RNDs.
 * One review per appointment (UNIQUE KEY on appointment_id).
 * Multiple reviews are allowed over a long-term relationship.
 *
 * @property int $rating 1–5 stars
 */
class Review extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'relationship_id',
        'appointment_id',
        'rating',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'rating'     => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(RndClientRelationship::class, 'relationship_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
