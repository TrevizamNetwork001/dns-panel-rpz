<?php

namespace App\Services;

use InvalidArgumentException;

class RpzFeedUrl
{
    /** Keep APP_URL as the policy zone and use an independent download origin. */
    public static function make(string $identifier): string
    {
        $path = '/rpz/'.rawurlencode($identifier).'.zone';
        $base = rtrim((string) config('rpz.feed_base_url'), '/');
        if ($base === '') {
            return url($path);
        }
        $parts = parse_url($base);
        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || ! empty($parts['path'])) {
            throw new InvalidArgumentException('RPZ_FEED_BASE_URL deve ser uma origem HTTPS, sem caminho ou credenciais.');
        }
        $host = strtolower(rtrim($parts['host'], '.'));
        $zone = strtolower(rtrim(app(RpzZoneBuilder::class)->panelHost(), '.'));
        if ($host === $zone || str_ends_with($host, '.'.$zone)) {
            throw new InvalidArgumentException('O endereço de download RPZ deve ficar fora do nome da zona.');
        }

        return $base.$path;
    }
}
