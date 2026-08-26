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

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function documentoFormatado(): ?string
    {
        if ($this->documento === null || $this->documento === '') {
            return null;
        }

        $documento = trim($this->documento);

        if (! preg_match('/^(?:\d{14}|\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2})$/', $documento)) {
            return $this->documento;
        }

        $digits = preg_replace('/\D/', '', $documento);

        return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digits);
    }

    /**
     * Existe pelo menos uma licenca ativa e vigente (dentro de starts_at/expires_at)?
     */
    public function possuiLicencaAtiva(): bool
    {
        return $this->licencas()
            ->where('status', 'active')
            ->whereDate('starts_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', now());
            })
            ->exists();
    }
}
