<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RblEvent extends Model
{
    protected $fillable = ['last_checked_value', 'rbl_target_id', 'rbl_list_id', 'status', 'first_seen_at', 'last_seen_at', 'resolved_at', 'last_response', 'notes', 'investigation_status', 'operator_notes', 'investigated_at', 'investigated_by'];

    protected function casts(): array
    {
        return ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'resolved_at' => 'datetime', 'investigated_at' => 'datetime'];
    }

    public function durationMinutes(): int
    {
        return $this->first_seen_at ? max(0, (int) $this->first_seen_at->diffInMinutes($this->resolved_at ?? now(), false)) : 0;
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(RblAlert::class)->orderByDesc('id');
    }

    public function delistRequests(): HasMany
    {
        return $this->hasMany(RblDelistRequest::class)->orderByDesc('id');
    }

    public function latestDelistRequest()
    {
        return $this->hasOne(RblDelistRequest::class)->latestOfMany();
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(RblTarget::class, 'rbl_target_id');
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(RblList::class, 'rbl_list_id');
    }

    public function investigator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investigated_by');
    }
}
