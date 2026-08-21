<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Empresa extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'documento',
        'email_contato',
        'status',
        'api_key',
    ];

    protected static function booted(): void
    {
        static::creating(function (Empresa $empresa) {
            if (empty($empresa->api_key)) {
                $empresa->api_key = Str::random(48);
            }
        });
    }

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

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
