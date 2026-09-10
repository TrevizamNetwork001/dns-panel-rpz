<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RblTargetScanState extends Model
{
    protected $fillable = [
        'rbl_target_id', 'cursor', 'cycle', 'total_ips', 'scanned_ips',
        'listed_ips', 'clean_ips', 'skipped_ips', 'error_ips',
        'started_at', 'completed_at', 'last_checked_at', 'summary',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime', 'completed_at' => 'datetime',
            'last_checked_at' => 'datetime', 'summary' => 'array',
        ];
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(RblTarget::class, 'rbl_target_id');
    }

    public function progressPercent(): float
    {
        return $this->total_ips > 0 ? round(($this->scanned_ips / $this->total_ips) * 100, 1) : 0;
    }

    public function pendingIps(): int
    {
        return max(0, $this->total_ips - $this->scanned_ips);
    }
}
