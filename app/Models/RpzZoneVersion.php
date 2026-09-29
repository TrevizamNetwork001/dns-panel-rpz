<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpzZoneVersion extends Model
{
    protected $fillable = [
        'rpz_zone_id',
        'revision',
        'serial',
        'content_hash',
        'file_hash',
        'domain_count',
        'rule_count',
        'excluded_count',
        'file_path',
        'file_size',
        'generated_at',
        'validated_at',
        'published_at',
        'status',
        'previous_version_id',
    ];

    protected $casts = [
        'revision' => 'integer',
        'serial' => 'integer',
        'domain_count' => 'integer',
        'rule_count' => 'integer',
        'excluded_count' => 'integer',
        'file_size' => 'integer',
        'generated_at' => 'datetime',
        'validated_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(RpzZone::class, 'rpz_zone_id');
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }
}
