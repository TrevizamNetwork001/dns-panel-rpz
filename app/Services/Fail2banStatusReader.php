<?php

namespace App\Services;

class Fail2banStatusReader
{
    public function read(): ?array
    {
        $path = config('security.fail2ban_snapshot');
        if (! is_string($path) || ! is_readable($path) || filesize($path) > 1048576) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        $timestamp = $data['generated_at'] ?? null;
        if (! is_int($timestamp) || $timestamp > time() + 30 || $timestamp < time() - 180
            || ! is_array($data['bans'] ?? null)) {
            return null;
        }
        foreach ($data['bans'] as $ban) {
            if (! is_array($ban) || ! in_array($ban['jail'] ?? null, ['sshd', 'panel-login'], true)
                || ! filter_var($ban['ip_address'] ?? '', FILTER_VALIDATE_IP)) {
                return null;
            }
        }

        return $data;
    }
}
