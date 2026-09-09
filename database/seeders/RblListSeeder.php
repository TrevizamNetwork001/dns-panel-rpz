<?php

namespace Database\Seeders;

use App\Models\RblList;
use Illuminate\Database\Seeder;

class RblListSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Spamhaus ZEN', 'zen.spamhaus.org', true, 'Sujeita às condições de acesso do provedor; resolver público pode ser recusado.'],
            ['SpamCop', 'bl.spamcop.net', true, null],
            ['Barracuda', 'b.barracudacentral.org', false, 'Ative após confirmar autorização/cadastro do resolver no provedor.'],
            ['SORBS', 'dnsbl.sorbs.net', false, 'Serviço descontinuado; mantido apenas como referência histórica.'],
        ] as [$name, $zone, $enabled, $description]) {
            RblList::firstOrCreate(['dns_zone' => $zone], [
                'name' => $name, 'type' => 'ip', 'enabled' => $enabled,
                'timeout_seconds' => 5, 'description' => $description,
            ]);
        }
    }
}
