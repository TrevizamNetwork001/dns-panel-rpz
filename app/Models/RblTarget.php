<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
