<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateRpzOperations;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RpzOperationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_list_contents_require_an_operations_credential_and_respect_company_visibility(): void
    {
        $company = Empresa::factory()->create();
        $otherCompany = Empresa::factory()->create();
        $ownList = Lista::factory()->for($company)->create(['origem' => 'manual']);
        $foreignList = Lista::factory()->for($otherCompany)->create(['origem' => 'manual']);
        $base = "/api/v1/operacao/empresas/{$company->id}/listas";

        $this->getJson("{$base}/{$ownList->id}/conteudo")->assertUnauthorized();

        $token = 'rpzo_'.bin2hex(random_bytes(32));
        Setting::set(AuthenticateRpzOperations::TOKEN_HASH_KEY, hash('sha256', $token));

        $this->withToken($token)->getJson("{$base}/{$ownList->id}/conteudo")->assertOk();
        $this->withToken($token)->getJson("{$base}/{$foreignList->id}/conteudo")->assertNotFound();
    }
}
