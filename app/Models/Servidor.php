<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Servidor extends Model
{
    use HasFactory;

    protected $table = 'servidores';

    protected $fillable = [
        'empresa_id',
        'nome',
        'status',
        'ip_restriction_enabled',
        'tipo_dns',
        'bloqueio_modo',
        'ip_v4',
        'ip_v6',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'ip_restriction_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Servidor $servidor) {
            if (empty($servidor->token)) {
                $servidor->token = Str::random(48);
            }
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function listas(): BelongsToMany
    {
        return $this->belongsToMany(Lista::class, 'lista_servidor');
    }

    public function allowedIps(): HasMany
    {
        return $this->hasMany(ServerAllowedIp::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(ServerSyncLog::class);
    }

    public function diasSemSincronizar(): ?int
    {
        if (! $this->last_synced_at) {
            return null;
        }

        return (int) $this->last_synced_at->diffInDays(now());
    }

    public function legacyRpzEndpointUrl(): string
    {
        return url('/rpz/'.$this->token.'.zone');
    }

    public function preferredRpzEndpointUrl(): string
    {
        return $this->empresa->rpzEndpointUrl() ?? $this->legacyRpzEndpointUrl();
    }

    public function ipAllowed(string $ip): bool
    {
        if (! $this->ip_restriction_enabled) {
            return true;
        }

        $rules = $this->allowedIps()->where('status', 'active')->pluck('ip_cidr');

        foreach ($rules as $cidr) {
            if (self::ipMatchesCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    public static function ipMatchesCidr(string $ip, string $cidr): bool
    {
        $cidr = trim($cidr);
        if ($cidr === '') {
            return false;
        }

        if (! str_contains($cidr, '/')) {
            $ipBinary = @inet_pton($ip);
            $ruleBinary = @inet_pton($cidr);

            return $ipBinary !== false && $ruleBinary !== false && hash_equals($ruleBinary, $ipBinary);
        }

        [$subnet, $maskLength] = array_pad(explode('/', $cidr, 2), 2, null);
        if ($subnet === null || $maskLength === null) {
            return false;
        }

        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);
        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $maskLength = (int) $maskLength;
        if ($maskLength < 0 || $maskLength > strlen($ipBinary) * 8) {
            return false;
        }
        $bytes = intdiv($maskLength, 8);
        $bits = $maskLength % 8;

        if ($bytes > 0 && substr($ipBinary, 0, $bytes) !== substr($subnetBinary, 0, $bytes)) {
            return false;
        }

        if ($bits > 0) {
            $mask = chr((0xFF << (8 - $bits)) & 0xFF);
            if ((substr($ipBinary, $bytes, 1) & $mask) !== (substr($subnetBinary, $bytes, 1) & $mask)) {
                return false;
            }
        }

        return true;
    }

    public static function validIpOrCidr(string $value): bool
    {
        [$ip, $mask] = array_pad(explode('/', trim($value), 2), 2, null);
        $binary = @inet_pton($ip);
        if ($binary === false) {
            return false;
        }

        return $mask === null || (ctype_digit($mask) && (int) $mask <= strlen($binary) * 8);
    }
}
