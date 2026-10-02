<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lista extends Model
{
    use HasFactory;

    protected $fillable = [
        'empresa_id',
        'nome',
        'descricao',
        'status',
        'origem',
        'fonte_externa',
        'fonte_url',
        'fonte_formato',
        'sync_ativo',
        'last_sync_at',
    ];

    protected $casts = [
        'sync_ativo' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function servidores(): BelongsToMany
    {
        return $this->belongsToMany(Servidor::class, 'lista_servidor')->withTimestamps();
    }

    public function dominios(): HasMany
    {
        return $this->hasMany(Dominio::class);
    }

    public function isExterna(): bool
    {
        return $this->origem === 'externa';
    }

    public function isAnatel(): bool
    {
        return $this->origem === 'anatel';
    }

    public function isManaged(): bool
    {
        return $this->isExterna() || $this->isAnatel();
    }

    /**
     * Lista manual irmã desta fonte gerenciada (ex.: "ANATEL" -> "ANATEL
     * (Manual)"), identificada por convenção de nome — é onde o usuário
     * deve cadastrar domínios manualmente pra essa fonte.
     */
    public function manualSibling(): ?self
    {
        if (! $this->isManaged()) {
            return null;
        }

        return static::where('empresa_id', $this->empresa_id)
            ->where('nome', $this->nome.' (Manual)')
            ->first();
    }

    public function anatelImports(): HasMany
    {
        return $this->hasMany(AnatelImport::class);
    }

    public function anatelExclusions(): HasMany
    {
        return $this->hasMany(AnatelExclusion::class);
    }
}
