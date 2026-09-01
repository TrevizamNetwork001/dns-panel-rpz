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
        'rpz_slug',
        'documento',
        'email_contato',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (Empresa $empresa): void {
            if ($empresa->rpz_slug) {
                return;
            }

            $base = substr(Str::slug($empresa->nome) ?: 'empresa', 0, 80);
            $slug = $base;
            $suffix = 2;
            while (static::where('rpz_slug', $slug)->exists()) {
                $tail = '-'.$suffix++;
                $slug = substr($base, 0, 80 - strlen($tail)).$tail;
            }
            $empresa->rpz_slug = $slug;
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
        return $this->licencas()->valid()->exists();
    }

    public function rpzEndpointUrl(): ?string
    {
        return $this->rpz_slug ? url('/rpz/'.$this->rpz_slug.'.zone') : null;
    }

}
