<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpzZone extends Model
{
    protected $fillable = [
        'zone_key',
        'scope_type',
        'empresa_id',
        'servidor_id',
        'zone_name',
        'last_serial',
        'revision',
        'content_hash',
        'domain_count',
        'rule_count',
        'excluded_count',
        'generated_at',
    ];

    protected $casts = [
        'last_serial' => 'integer',
        'revision' => 'integer',
        'domain_count' => 'integer',
        'rule_count' => 'integer',
        'excluded_count' => 'integer',
        'generated_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }
}
