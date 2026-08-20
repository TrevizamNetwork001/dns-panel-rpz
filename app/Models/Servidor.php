<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Servidor extends Model
{
    use HasFactory;

    protected $table = 'servidores';

    protected $fillable = [
        'empresa_id',
        'nome',
        'status',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Servidor $servidor) {
            if (empty($servidor->token)) {
                $servidor->token = Str::random(48);
            }
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function listas(): BelongsToMany
    {
        return $this->belongsToMany(Lista::class, 'lista_servidor');
    }
}
