<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LogSecurityBan extends Command
{
    protected $signature = 'security:log-ban {ip} {jail} {action}';

    protected $description = 'Registra um evento de ban/unban do fail2ban (chamado pelo próprio fail2ban via action hook)';

    public function handle(): int
    {
        $ip = $this->argument('ip');
        $jail = $this->argument('jail');
        $action = $this->argument('action');

        if (! in_array($action, ['ban', 'unban'], true)) {
            $this->error('Ação inválida: ' . $action);

            return self::FAILURE;
        }

        DB::table('security_bans')->insert([
            'ip_address' => $ip,
            'jail' => $jail,
            'action' => $action,
            'created_at' => now(),
        ]);

        AuditLog::record(
            $action === 'ban' ? 'security.ip_banned' : 'security.ip_unbanned',
            $action === 'ban'
                ? "IP {$ip} bloqueado pelo fail2ban (jail {$jail}) após tentativas de força bruta"
                : "IP {$ip} desbloqueado pelo fail2ban (jail {$jail})",
            null,
            'security_ban',
            null
        );

        return self::SUCCESS;
    }
}
