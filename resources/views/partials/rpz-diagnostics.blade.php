<div class="field-group" style="margin-top:18px">
    <strong>Conferir o RPZ no Unbound</strong>
    <p style="color:var(--text-muted);font-size:12px;line-height:1.6">Execute no servidor DNS do cliente. A primeira consulta deve retornar <code>NXDOMAIN</code> em A e AAAA; <code>list_auth_zones</code> mostra o serial local e se a zona expirou.</p>
    <pre class="endpoint-config-snippet" id="rpz-diagnostics-commands">unbound-control list_auth_zones
dig @127.0.0.1 {{ escapeshellarg('blocktest.'.$diagnosticZone) }} A +noall +comments +answer
dig @127.0.0.1 {{ escapeshellarg('blocktest.'.$diagnosticZone) }} AAAA +noall +comments +answer
unbound-control stats_noreset</pre>
    <button type="button" class="button button-secondary" {{ $copyAttribute }}="rpz-diagnostics-commands">Copiar diagnóstico</button>
    <p style="color:var(--text-muted);font-size:12px;line-height:1.6">Nos contadores <code>num.rpz.action.*</code>, veja as ações aplicadas: <code>rpz-nxdomain</code> (bloqueio), <code>rpz-passthru</code> (exceção) e <code>rpz-local-data</code> (resposta local, incluindo redirecionamento). <code>stats_noreset</code> preserva os contadores; eles somam todas as zonas e não identificam uma empresa. Se não aparecerem, confira <code>extended-statistics: yes</code> dentro de <code>server:</code>; contadores zerados podem ser omitidos.</p>
    <p style="color:var(--text-muted);font-size:12px;line-height:1.6">Para investigar um domínio específico, habilite temporariamente <code>rpz-log: yes</code> e <code>rpz-log-name: "dns-panel-rpz"</code> no bloco <code>rpz:</code>, valide a configuração e consulte os logs do Unbound após a consulta. O registro <code>rpz: applied</code> identifica a ação e a regra. Desative o log ao terminar.</p>
    <small>Baixar o arquivo confirma a entrega. O serial local confirma a versão carregada; a consulta e o log confirmam o comportamento naquele teste. O controle remoto precisa estar habilitado no Unbound.</small>
</div>
