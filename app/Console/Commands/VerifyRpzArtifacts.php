<?php

namespace App\Console\Commands;

use App\Models\RpzZone;
use App\Services\RpzArtifactInspector;
use Illuminate\Console\Command;

class VerifyRpzArtifacts extends Command
{
    protected $signature = 'rpz:verify {zone_key? : Ex.: server:15 ou company:11; omita para verificar todas}';

    protected $description = 'Audita integridade, regras e canário dos RPZ publicados, sem alterar dados';

    public function handle(RpzArtifactInspector $inspector): int
    {
        $key = $this->argument('zone_key');
        if ($key !== null && ! preg_match('/^(server|company):[1-9][0-9]*$/D', $key)) {
            $this->error('Use server:ID ou company:ID.');

            return self::INVALID;
        }
        $zones = RpzZone::with('currentVersion')->when($key, fn ($query) => $query->where('zone_key', $key))->orderBy('zone_key')->get();
        if ($zones->isEmpty()) {
            $this->error('Nenhuma zona encontrada.');

            return self::FAILURE;
        }
        $failed = false;
        foreach ($zones as $zone) {
            $result = $inspector->inspect($zone);
            $this->line($zone->zone_key.' | serial '.($result['serial'] ?? '-').' | '.($result['ok'] ? 'OK' : 'FALHA'));
            $this->line('Regras (sem canário): NXDOMAIN='.$result['actions']['nxdomain'].'; PASSTHRU='.$result['actions']['passthru'].'; redirecionamento='.$result['actions']['redirect']);
            foreach ($result['errors'] as $error) {
                $this->error($error);
            }
            $failed = $failed || ! $result['ok'];
        }
        $this->line('Verificação dos arquivos no painel. Confirme carregamento e aplicação no Unbound do cliente.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
