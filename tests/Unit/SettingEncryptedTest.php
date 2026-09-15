<?php

namespace Tests\Unit;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingEncryptedTest extends TestCase
{
    use RefreshDatabase;

    public function test_encrypted_value_round_trips(): void
    {
        Setting::setEncrypted('chave_teste', 'valor-secreto-123');

        $this->assertSame('valor-secreto-123', Setting::getEncrypted('chave_teste'));
    }

    public function test_stored_value_is_not_plaintext_in_the_database(): void
    {
        Setting::setEncrypted('chave_teste', 'valor-secreto-123');

        $bruto = Setting::get('chave_teste');

        $this->assertNotSame('valor-secreto-123', $bruto);
        $this->assertStringNotContainsString('valor-secreto-123', $bruto);
    }

    public function test_legacy_plaintext_value_still_reads_correctly(): void
    {
        // Simula um valor salvo antes da criptografia existir.
        Setting::set('chave_legado', 'token-antigo-sem-criptografia');

        $this->assertSame('token-antigo-sem-criptografia', Setting::getEncrypted('chave_legado'));
    }

    public function test_returns_default_when_key_missing(): void
    {
        $this->assertNull(Setting::getEncrypted('nao_existe'));
        $this->assertSame('padrao', Setting::getEncrypted('nao_existe', 'padrao'));
    }

    public function test_setting_null_clears_the_value(): void
    {
        Setting::setEncrypted('chave_teste', 'algo');
        Setting::setEncrypted('chave_teste', null);

        $this->assertNull(Setting::getEncrypted('chave_teste'));
    }
}
