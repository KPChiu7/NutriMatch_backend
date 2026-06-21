<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nutrition education materials uploaded by RNDs and administrators.
 *
 * Visibility (no relationship_id/assignment table exists on this table —
 * derived entirely at query time, see Api\Client\ResourceController):
 *  - Admin-uploaded resources (uploaded_by user with role=admin) are
 *    visible to ALL clients, platform-wide.
 *  - RND-uploaded resources are visible only to that RND's own clients
 *    (any RndClientRelationship, regardless of status), forming a
 *    shared library across all of that RND's clients.
 *
 * @property string $type article|pdf|video|link
 */
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
