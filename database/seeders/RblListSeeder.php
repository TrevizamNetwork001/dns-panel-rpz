<?php

namespace Database\Seeders;

use App\Models\RblList;
use Illuminate\Database\Seeder;

class RblListSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Spamhaus ZEN', 'zen.spamhaus.org', true, 'Sujeita às condições de acesso do provedor; resolver público pode ser recusado.', 'Verifique o IP no checker oficial e siga somente o fluxo indicado para a listagem encontrada.'],
            ['SpamCop', 'bl.spamcop.net', true, null, 'Consulte a página da listagem. A remoção pode ocorrer após cessarem os reports, conforme a política vigente do provedor.'],
            ['Barracuda', 'b.barracudacentral.org', false, 'Ative após confirmar autorização/cadastro do resolver no provedor.', 'Consulte o IP e use manualmente o formulário oficial de removal request, após revisar as exigências vigentes.'],
            ['SORBS', 'dnsbl.sorbs.net', false, 'Serviço descontinuado; mantido apenas como referência histórica.', 'Revise a disponibilidade e o fluxo vigente do provedor antes de qualquer ação; esta entrada pode ser apenas histórica.'],
        ] as [$name, $zone, $enabled, $description, $instructions]) {
            $list = RblList::firstOrCreate(['dns_zone' => $zone], [
                'name' => $name, 'type' => 'ip', 'enabled' => $enabled,
                'timeout_seconds' => 5, 'description' => $description,
                'delist_instructions' => $instructions,
                'delist_requires_manual_review' => true,
            ]);
            if (! $list->wasRecentlyCreated && $list->delist_instructions === null) {
                $list->update(['delist_instructions' => $instructions]);
            }
        }
    }
}
