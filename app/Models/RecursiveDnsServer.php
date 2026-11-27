<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecursiveDnsServer extends Model
{
    protected $fillable = [
        'empresa_id', 'name', 'hostname', 'ipv4', 'ipv6', 'ssh_family', 'ssh_port', 'ssh_user',
        'ssh_fingerprint', 'ssh_password', 'diagnostic', 'diagnosed_at',
    ];

    protected $hidden = ['ssh_password'];

    protected function casts(): array
    {
        return ['ssh_password' => 'encrypted', 'diagnostic' => 'array', 'diagnosed_at' => 'datetime'];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function sshAddress(): string
    {
        return $this->ssh_family == 6 ? $this->ipv6 : $this->ipv4;
    }
}
