<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dominio extends Model
{
    use HasFactory;

    protected $fillable = [
        'lista_id',
        'dominio',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function lista(): BelongsTo
    {
        return $this->belongsTo(Lista::class);
    }
}
