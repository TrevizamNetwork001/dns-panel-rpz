<?php

namespace App\Services\Rbl;

use App\Models\RblTarget;

class TargetExpansion
{
    public function plan(RblTarget $target, ?int $largeBatchLimit = null): array
    {
        if ($target->type === 'ip' && filter_var($target->value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ['ips' => [$target->value], 'reason' => null, 'total_ips' => 1, 'cursor' => 0, 'next_cursor' => 0, 'cycle_complete' => true];
        }
        $parts = explode('/', $target->value);
        if ($target->type !== 'cidr' || count($parts) !== 2 || ! filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || ! ctype_digit($parts[1]) || (int) $parts[1] > 32) {
            return ['ips' => [], 'reason' => 'Consulta disponível apenas para IPv4 individual ou CIDR IPv4.', 'total_ips' => 0, 'cursor' => 0, 'next_cursor' => 0, 'cycle_complete' => false];
        }
        $prefix = (int) $parts[1];
        $size = 2 ** (32 - $prefix);
        $legacyLimit = max(1, min(8, (int) config('rbl.max_cidr_ips', 8)));
        $maxTotal = max($legacyLimit, min(65536, (int) config('rbl.max_cidr_total_ips', 1024)));
        $minPrefix = max(16, min(32, (int) config('rbl.min_cidr_prefix', 22)));
        if ($size > $legacyLimit && (! config('rbl.large_cidr_enabled', true) || $size > $maxTotal || $prefix < $minPrefix)) {
            return ['ips' => [], 'reason' => "CIDR excede o limite incremental de {$maxTotal} endereços (prefixo mínimo /{$minPrefix}).", 'total_ips' => $size, 'cursor' => 0, 'next_cursor' => 0, 'cycle_complete' => false];
        }
        $base = (int) (floor((int) sprintf('%u', ip2long($parts[0])) / $size) * $size);
        $cursor = min((int) ($target->scanState?->cursor ?? 0), $size - 1);
        $batch = $size <= $legacyLimit ? $size : max(1, min(256, $largeBatchLimit ?? (int) config('rbl.batch_ips_per_run', 8)));
        $count = min($batch, $size - $cursor);
        $ips = [];
        for ($i = 0; $i < $count; $i++) {
            $ips[] = long2ip($base + $cursor + $i);
        }
        $nextCursor = $cursor + $count;

        return ['ips' => $ips, 'reason' => null, 'total_ips' => $size, 'cursor' => $cursor, 'next_cursor' => $nextCursor >= $size ? 0 : $nextCursor, 'cycle_complete' => $nextCursor >= $size];
    }
}
