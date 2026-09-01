<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnatelImportDomain extends Model
{
    protected $fillable = ['anatel_import_id', 'domain', 'result'];

    public function import(): BelongsTo
    {
        return $this->belongsTo(AnatelImport::class, 'anatel_import_id');
    }
}
