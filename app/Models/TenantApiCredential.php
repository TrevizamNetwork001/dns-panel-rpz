<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantApiCredential extends Model
{
    protected $fillable = ['empresa_id', 'servidor_id', 'user_id', 'name', 'token_hash', 'scope', 'expires_at'];

    protected $hidden = ['token_hash'];

    protected $casts = ['expires_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
