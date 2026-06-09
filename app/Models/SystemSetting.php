<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Platform-wide key-value configuration store.
 * Allows admins to change settings without code deployments.
 *
 * Keys use dot notation: billing.commission_pct, app.maintenance_mode
 */
class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'description',
        'updated_by',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Retrieve a setting value by key, with an optional default.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }
}
