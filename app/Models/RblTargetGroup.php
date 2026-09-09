<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class RblTargetGroup extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'category', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function events(): HasManyThrough
    {
        return $this->hasManyThrough(RblEvent::class, RblTarget::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(RblTarget::class);
    }
}
