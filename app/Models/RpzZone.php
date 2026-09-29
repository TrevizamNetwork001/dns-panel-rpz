<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'current_version_id',
        'desired_generation',
        'built_generation',
    ];

    protected $casts = [
        'last_serial' => 'integer',
        'revision' => 'integer',
        'domain_count' => 'integer',
        'rule_count' => 'integer',
        'excluded_count' => 'integer',
        'generated_at' => 'datetime',
        'desired_generation' => 'integer',
        'built_generation' => 'integer',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(RpzZoneVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RpzZoneVersion::class);
    }

    /**
     * A zona esta "suja": houve mutacao relevante ainda nao refletida no
     * artefato publicado. Nome deliberadamente NAO "isDirty()" -- colidiria
     * com o metodo nativo do Eloquent (rastreio de atributos alterados em
     * memoria), que tem semantica completamente diferente.
     */
    public function needsRebuild(): bool
    {
        return $this->desired_generation > $this->built_generation;
    }
}
