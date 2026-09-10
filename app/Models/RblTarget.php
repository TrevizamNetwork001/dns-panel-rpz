<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RblTarget extends Model
{
    protected $fillable = ['rbl_target_group_id', 'name', 'type', 'value', 'category', 'description', 'enabled', 'last_status', 'last_checked_at'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'last_checked_at' => 'datetime'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(RblTargetGroup::class, 'rbl_target_group_id');
    }

    public function scopeMonitorable($query)
    {
        return $query->where('enabled', true)->where(fn ($q) => $q->whereNull('rbl_target_group_id')->orWhereHas('group', fn ($g) => $g->where('enabled', true)));
    }

    public function checks(): HasMany
    {
        return $this->hasMany(RblCheck::class, 'rbl_target_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(RblEvent::class, 'rbl_target_id');
    }

    public function scanState(): HasOne
    {
        return $this->hasOne(RblTargetScanState::class, 'rbl_target_id');
    }

    public function cidrTotalIps(): int
    {
        if ($this->type !== 'cidr') {
            return 0;
        }
        $parts = explode('/', $this->value);

        return count($parts) === 2 && filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && ctype_digit($parts[1]) && (int) $parts[1] <= 32
            ? (int) (2 ** (32 - (int) $parts[1])) : 0;
    }
}
