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
        'dominios_count',
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
