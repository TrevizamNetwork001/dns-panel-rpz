<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerSyncLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'servidor_id',
        'ip_address',
        'user_agent',
        'dominios_count',
        'request_id',
        'zone_key',
        'revision',
        'content_hash',
        'rule_count',
        'excluded_count',
        'duration_ms',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }
}
