<?php

return [
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
];
