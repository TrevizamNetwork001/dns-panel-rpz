<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RblDelistRequest extends Model
{
    public const STATUSES = [
        'not_requested', 'instructions_viewed', 'requested', 'waiting',
        'accepted', 'rejected', 'not_applicable', 'cancelled',
    ];

    public const STATUS_LABELS = [
        'not_requested' => 'Não solicitado',
        'instructions_viewed' => 'Instruções visualizadas',
        'requested' => 'Solicitado',
        'waiting' => 'Aguardando',
        'accepted' => 'Aceito',
        'rejected' => 'Rejeitado',
        'not_applicable' => 'N/A',
        'cancelled' => 'Cancelado',
    ];

    protected $fillable = ['rbl_event_id', 'user_id', 'status', 'requested_at', 'request_url', 'protocol', 'contact_email', 'notes'];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(RblEvent::class, 'rbl_event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
