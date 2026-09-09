<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RblCheck extends Model
{
    protected $fillable = ['rbl_run_id', 'rbl_target_id', 'rbl_list_id', 'checked_value', 'query', 'status', 'response', 'response_text', 'error_message', 'checked_at'];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(RblTarget::class, 'rbl_target_id');
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(RblList::class, 'rbl_list_id');
    }
}
