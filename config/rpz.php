<?php

return [
    // Independent HTTPS origin; do not change APP_URL or existing zone names.
    'feed_base_url' => env('RPZ_FEED_BASE_URL'),
    'block_page_host' => env('RPZ_BLOCK_PAGE_HOST'),
    // Preparation environments must block dynamic delivery as well as artifacts.
    'endpoints_enabled' => (bool) env('RPZ_ENDPOINTS_ENABLED', true),
    'publication_enabled' => (bool) env('RPZ_PUBLICATION_ENABLED', true),
    /*
    |--------------------------------------------------------------------------
    | Artefatos RPZ estaticos (RPZ-BUILD-1)
    |--------------------------------------------------------------------------
    |
    | Quando false (padrao), o endpoint /rpz/{token|slug}.zone continua
    | gerando a zona dinamicamente a cada requisicao, exatamente como antes
    | da fase RPZ-BUILD-1 -- nenhuma mudanca de comportamento.
    |
    | Quando true, o endpoint passa a servir o arquivo .zone ja publicado
    | (rpz_zones.current_version_id) quando existir, sem tocar a tabela
    | dominios no GET. So deve ser ativado depois que TODOS os produtores
    | relevantes de mudanca de conteudo (listas, dominios, vinculos,
    | sincronizacao externa, importacao/exclusao ANATEL, bloqueio_modo,
    | status de servidor/empresa) estiverem cobertos por RpzDirtyMarker --
    | caso contrario um artefato pode ficar servido silenciosamente
    | desatualizado. Ver RUNBOOK.md.
    |
    | Zonas sem versao publicada ainda (current_version_id null) sempre
    | caem no caminho dinamico, mesmo com a flag ligada -- fallback seguro,
    | sem quebrar clientes existentes durante a transicao.
    |
    */
    'static_artifacts_enabled' => (bool) env('RPZ_STATIC_ARTIFACTS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | "Primary" nativo do RPZ (RPZ-UNBOUND-LAB-1, producao real)
    |--------------------------------------------------------------------------
    |
    | Servico UDP dedicado (rpz:primary-serve) que responde sondagens SOA-only
    | de servidores Unbound configurados com `primary:` + `url:`, evitando
    | download desnecessario quando o serial nao mudou. Ver
    | app/Services/RpzPrimaryResponder.php e app/Console/Commands/RpzPrimaryServe.php.
    | Desligado por padrao ate o piloto (DNS-03) validar em producao real.
    |
    */
    // Servers whose HTTPS URL serves the company zone rather than their own zone.
    'primary_company_server_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('RPZ_PRIMARY_COMPANY_SERVER_IDS', ''))))),
    'primary_enabled' => (bool) env('RPZ_PRIMARY_ENABLED', false),
    // Lista explicita de servidores que podem receber NOTIFY durante o piloto.
    'notify_server_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('RPZ_NOTIFY_SERVER_IDS', ''))))),
    'notify_enabled' => (bool) env('RPZ_NOTIFY_ENABLED', false),
    'notify_ipv6_enabled' => (bool) env('RPZ_NOTIFY_IPV6_ENABLED', true),
    'notify_attempts' => (int) env('RPZ_NOTIFY_ATTEMPTS', 3),
    'notify_timeout' => (float) env('RPZ_NOTIFY_TIMEOUT', 1),
    'primary_port' => (int) env('RPZ_PRIMARY_PORT', 5353),
    'primary_bind' => env('RPZ_PRIMARY_BIND', '0.0.0.0'),
];
