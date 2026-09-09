<?php

namespace App\Services\Rbl;

use App\Models\RblTarget;

class TargetExpansion
{
    public function plan(RblTarget $target): array
    {
        if ($target->type === 'ip' && filter_var($target->value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ['ips' => [$target->value], 'reason' => null];
        }
        $parts = explode('/', $target->value);
        if ($target->type !== 'cidr' || count($parts) !== 2 || ! filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || ! ctype_digit($parts[1]) || (int) $parts[1] > 32) {
            return ['ips' => [], 'reason' => 'Consulta disponível apenas para IPv4 individual ou CIDR IPv4 pequeno.'];
        }
        $size = 2 ** (32 - (int) $parts[1]);
        $limit = max(1, min(8, (int) config('rbl.max_cidr_ips', 8)));
        if ($size > $limit) {
            return ['ips' => [], 'reason' => "CIDR excede o limite de {$limit} endereços por alvo."];
        }
        $base = (int) (floor((int) sprintf('%u', ip2long($parts[0])) / $size) * $size);
        $ips = [];
        for ($i = 0; $i < $size; $i++) {
            $ips[] = long2ip($base + $i);
        }

        return ['ips' => $ips, 'reason' => null];
    }
}
