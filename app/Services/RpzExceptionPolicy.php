<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/** Normalize company allow-subtrees without ever modifying shared sources. */
class RpzExceptionPolicy
{
    private array $exceptions = [];

    public static function normalize(string $domain): string
    {
        $domain = strtolower(rtrim(trim($domain), '.'));
        if (preg_match('/[^\x20-\x7e]/', $domain) && function_exists('idn_to_ascii')) {
            $domain = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: '';
        }
        // Do not strip www: an exception must not silently allow its parent.
        if (filter_var($domain, FILTER_VALIDATE_IP) || ! preg_match('/^(?=.{1,253}$)(?!-)[a-z0-9-]{1,63}(?<!-)(?:\.(?!-)[a-z0-9-]{1,63}(?<!-))+$/D', $domain)) {
            throw new InvalidArgumentException('Informe um domínio válido, sem URL ou curinga.');
        }

        return $domain;
    }

    public static function validateForZone(string $domain, string $host): string
    {
        $domain = self::normalize($domain);
        $canary = 'blocktest.'.$host;
        if ($canary === $domain || str_ends_with($canary, '.'.$domain)) {
            throw new InvalidArgumentException('A exceção não pode liberar o domínio de teste do painel.');
        }
        if (strlen('*.'.$domain.'.'.rtrim($host, '.')) > 253) {
            throw new InvalidArgumentException('Domínio longo demais para uma exceção RPZ com subdomínios.');
        }

        return $domain;
    }

    public function __construct(?int $empresaId, string $host)
    {
        if ($empresaId === null || ! Schema::hasTable('empresa_domain_exceptions')) {
            return;
        }
        foreach (DB::table('empresa_domain_exceptions')->where('empresa_id', $empresaId)->pluck('dominio') as $stored) {
            $this->exceptions[self::validateForZone($stored, $host)] = true;
        }
        foreach (array_keys($this->exceptions) as $domain) {
            for ($parent = $this->parent($domain); $parent !== null; $parent = $this->parent($parent)) {
                if (isset($this->exceptions[$parent])) {
                    unset($this->exceptions[$domain]);
                    break;
                }
            }
        }
        ksort($this->exceptions, SORT_STRING);
    }

    public function allows(string $domain): bool
    {
        if ($this->exceptions === []) {
            return false;
        }
        do {
            if (isset($this->exceptions[$domain])) {
                return true;
            }
            $domain = $this->parent($domain);
        } while ($domain !== null);

        return false;
    }

    public function rules(): array
    {
        $rules = [];
        foreach (array_keys($this->exceptions) as $exception) {
            $rules[$exception] = 'rpz-passthru.';
            $rules['*.'.$exception] = 'rpz-passthru.';
        }
        ksort($rules, SORT_STRING);

        return $rules;
    }

    public function domains(): array
    {
        return array_keys($this->exceptions);
    }

    private function parent(string $domain): ?string
    {
        $dot = strpos($domain, '.');

        return $dot === false ? null : substr($domain, $dot + 1);
    }
}
