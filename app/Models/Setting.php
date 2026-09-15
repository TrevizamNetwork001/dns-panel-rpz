<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Igual a get(), mas descriptografa o valor (usado pra credenciais
     * sensíveis: token do Telegram, chaves do R2). Valores legados salvos
     * antes da criptografia existir (texto puro) continuam legíveis —
     * ficam criptografados assim que forem salvos de novo pela tela.
     */
    public static function getEncrypted(string $key, ?string $default = null): ?string
    {
        $raw = static::get($key);
        if ($raw === null) {
            return $default;
        }

        try {
            return Crypt::decryptString($raw);
        } catch (DecryptException) {
            return $raw;
        }
    }

    public static function setEncrypted(string $key, ?string $value): void
    {
        static::set($key, $value === null ? null : Crypt::encryptString($value));
    }
}
