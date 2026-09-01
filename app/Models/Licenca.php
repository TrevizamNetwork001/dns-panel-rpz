<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Licenca extends Model
{
    use HasFactory;

    protected $fillable = [
        'empresa_id',
        'starts_at',
        'expires_at',
        'max_servidores',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'expires_at' => 'date',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereDate('starts_at', '<=', today())
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today());
            });
    }

    public function isValid(): bool
    {
        return $this->status === 'active'
            && $this->starts_at->startOfDay()->lte(today())
            && ($this->expires_at === null || $this->expires_at->startOfDay()->gte(today()));
    }

    public function expirationSummary(): ?string
    {
        if ($this->expires_at === null) {
            return null;
        }

        $days = (int) today()->startOfDay()->diffInDays($this->expires_at->startOfDay(), false);

        return match (true) {
            $days > 1 => "{$days} dias restantes",
            $days === 1 => '1 dia restante',
            $days === 0 => 'Expira hoje',
            $days === -1 => 'Expirada há 1 dia',
            default => 'Expirada há '.abs($days).' dias',
        };
    }
}
