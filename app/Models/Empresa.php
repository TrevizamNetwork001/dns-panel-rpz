<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'documento',
        'email_contato',
        'status',
    ];

    public function servidores(): HasMany
    {
        return $this->hasMany(Servidor::class);
    }

    public function listas(): HasMany
    {
        return $this->hasMany(Lista::class);
    }

    public function licencas(): HasMany
    {
        return $this->hasMany(Licenca::class);
    }
}
