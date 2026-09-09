<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RblList extends Model
{
    protected $fillable = ['name', 'dns_zone', 'type', 'enabled', 'timeout_seconds', 'description'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'timeout_seconds' => 'integer'];
    }

    public function checks(): HasMany
    {
        return $this->hasMany(RblCheck::class, 'rbl_list_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(RblEvent::class, 'rbl_list_id');
    }
}
