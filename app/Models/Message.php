<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * In-platform clinical messaging scoped to RND-client relationships.
 * deleted_at enables soft-delete for RA 10173 audit compliance.
 * read_at provides a precise timestamp instead of a simple boolean.
 *
 * @property string $message_type text|file|image|system
 */
class Message extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'relationship_id',
        'sender_id',
        'message',
        'message_type',
        'attachment_url',
        'attachment_type',
        'is_read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read'    => 'boolean',
            'read_at'    => 'datetime',
            'created_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(RndClientRelationship::class, 'relationship_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
