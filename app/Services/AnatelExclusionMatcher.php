<?php
namespace App\Services;

use App\Models\AnatelExclusion;

class AnatelExclusionMatcher
{
    public function isValidRegex(string $value): bool
    {
        if ($value === '' || strlen($value) > 1000) return false;
        set_error_handler(static fn () => true);
        try { return preg_match($this->pattern($value), '') !== false; } finally { restore_error_handler(); }
    }

    public function matches(string $domain, iterable $exclusions): bool
    {
        foreach ($exclusions as $exclusion) {
            if (! $exclusion->active) continue;
            if ($exclusion->type === 'exact' && hash_equals($exclusion->value, $domain)) return true;
            if ($exclusion->type === 'regex' && $this->isValidRegex($exclusion->value) && preg_match($this->pattern($exclusion->value), $domain) === 1) return true;
        }
        return false;
    }

    private function pattern(string $value): string { return '~(?:'.$value.')~uD'; }
}
