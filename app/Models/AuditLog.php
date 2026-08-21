<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'empresa_id',
        'action',
        'target_type',
        'target_id',
        'description',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public static function record(
        string $action,
        ?string $description = null,
        ?int $empresaId = null,
        ?string $targetType = null,
        ?int $targetId = null
    ): void {
        self::create([
            'user_id' => Auth::id(),
            'empresa_id' => $empresaId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'description' => $description,
            'ip_address' => RequestFacade::ip(),
            'created_at' => now(),
        ]);
    }

    public static function bucket(string $action): string
    {
        $action = strtolower($action);

        if (str_contains($action, 'auth') || str_contains($action, 'login') || str_contains($action, 'security')) {
            if (str_contains($action, 'fail') || str_contains($action, 'throttle') || str_contains($action, 'invalid') || str_contains($action, 'unauthorized')) {
                return 'danger';
            }

            return 'warning';
        }

        if (str_starts_with($action, 'health.')) {
            if ($action === 'health.ok') {
                return 'muted';
            }

            return str_contains($action, 'site_down') || str_contains($action, 'disk_low') ? 'danger' : 'warning';
        }

        if (str_starts_with($action, 'sugestao.') || str_contains($action, 'ip_restriction') || str_contains($action, 'ips.')) {
            return 'info';
        }

        if (str_contains($action, 'destroy') || str_contains($action, 'removid') || str_contains($action, 'rejeit')) {
            return 'warning';
        }

        return 'muted';
    }

    public static function bucketLabel(string $bucket): string
    {
        return match ($bucket) {
            'danger' => 'Alto',
            'warning' => 'Médio',
            'info' => 'Info',
            default => 'Baixo',
        };
    }
}
