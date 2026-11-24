@php($apiBase = url('/api/v1'))
<p>Use HTTPS e guarde o token no gerenciador de segredos da integração. Os exemplos usam Bash e curl.</p>
<ol>
    <li>
        <strong>Gere uma credencial e liste as fontes.</strong>
        <p>Comece com Somente consulta. Digite o token quando o terminal pedir.</p>
        <pre><code>API_BASE='{{ $apiBase }}'
read -rsp 'Token RPZ: ' RPZ_TOKEN; echo
curl -fsS -H "Authorization: Bearer $RPZ_TOKEN" "$API_BASE/listas"</code></pre>
    </li>
    <li>
        <strong>Consulte os bloqueios.</strong> Use <code>dominio</code> para busca exata, <code>ativo=1</code> para entradas ativas, <code>lista_id</code> para uma fonte e <code>page</code> e <code>per_page</code> para paginação.
        <pre><code>curl -fsS -H "Authorization: Bearer $RPZ_TOKEN" \
  "$API_BASE/bloqueios?dominio=exemplo.com&amp;ativo=1"</code></pre>
        <p>Uma regra ativa é uma entrada configurada. A zona depende das fontes ativas vinculadas aos servidores e da política de exceções.</p>
    </li>
    <li>
        <strong>Cadastre um bloqueio.</strong> Use uma credencial de Consulta e edição e substitua <code>12</code> pelo ID de uma fonte com <code>editavel: true</code>. O retorno <code>201</code> traz o ID da regra. <code>409</code> indica domínio repetido ou conflito de vínculos.
        <pre><code>curl -fsS -X POST -H "Authorization: Bearer $RPZ_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"dominio":"exemplo.com"}' "$API_BASE/listas/12/bloqueios"</code></pre>
    </li>
    <li>
        <strong>Acompanhe a atualização da zona e dos servidores.</strong>
        <pre><code>curl -fsS -H "Authorization: Bearer $RPZ_TOKEN" "$API_BASE/publicacao"
curl -fsS -H "Authorization: Bearer $RPZ_TOKEN" "$API_BASE/servidores"</code></pre>
        <p><code>publicacao</code> informa o estado da zona empresarial e seu último serial publicado. A alteração entra no fluxo normal de geração das zonas vinculadas. Em <code>servidores</code>, a última entrega indica que o painel atendeu uma solicitação de feed RPZ. A confirmação de carregamento é reportada separadamente e pode aparecer como <code>null</code>.</p>
    </li>
    <li>
        <strong>Confirme a versão aplicada.</strong> Configure seu monitor para ler o serial da zona carregada no resolvedor. Envie esse serial com uma credencial de confirmação gerada para aquele servidor. Use <code>zona</code> igual a <code>empresa</code> para a zona empresarial ou <code>servidor</code> para a zona individual.
        <pre><code>curl -fsS -X PUT -H "Authorization: Bearer $RPZ_REPORT_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"zona":"empresa","serial":105}' "$API_BASE/servidores/confirmacao"</code></pre>
    </li>
    <li>
        <strong>Edite ou remova a regra.</strong> Substitua <code>34</code> pelo ID retornado no cadastro.
        <pre><code>curl -fsS -X PATCH -H "Authorization: Bearer $RPZ_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"ativo":false}' "$API_BASE/bloqueios/34"
curl -fsS -X DELETE -H "Authorization: Bearer $RPZ_TOKEN" "$API_BASE/bloqueios/34"</code></pre>
    </li>
</ol>
<p>Em caso de erro, confira <code>401</code> para credencial inválida ou expirada, <code>403</code> para permissão insuficiente, <code>404</code> para recurso indisponível, <code>422</code> para dados inválidos e <code>429</code> para excesso de chamadas. O limite é de 120 chamadas por minuto por IP. Para integrar um Hermes no futuro, configure as operações desejadas a partir do OpenAPI e use uma credencial própria com a permissão necessária.</p>
