<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnatelImport extends Model
{
    protected $fillable = ['lista_id', 'user_id', 'original_filename', 'storage_path', 'sha256', 'size_bytes', 'pages', 'candidates_count', 'valid_count', 'invalid_count', 'new_count', 'existing_count', 'reactivated_count', 'excluded_count', 'unblocked_count', 'status', 'progress', 'error', 'started_at', 'finished_at'];

    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime'];

    public function lista(): BelongsTo
    {
        return $this->belongsTo(Lista::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(AnatelImportDomain::class);
    }
}
