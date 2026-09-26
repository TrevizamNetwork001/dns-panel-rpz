<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'empresa_id',
        'action',
        'target_type',
        'target_id',
        'description',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public static function record(
        string $action,
        ?string $description = null,
        ?int $empresaId = null,
        ?string $targetType = null,
        ?int $targetId = null
    ): void {
        self::create([
            'user_id' => Auth::id(),
            'empresa_id' => $empresaId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'description' => $description,
            'ip_address' => RequestFacade::ip(),
            'created_at' => now(),
        ]);
    }

    public static function bucket(string $action): string
    {
        $action = strtolower($action);

        if (str_contains($action, 'auth') || str_contains($action, 'login') || str_contains($action, 'security')) {
            if (str_contains($action, 'fail') || str_contains($action, 'throttle') || str_contains($action, 'invalid') || str_contains($action, 'unauthorized')) {
                return 'danger';
            }

            return 'warning';
        }

        if (str_starts_with($action, 'health.')) {
            if ($action === 'health.ok') {
                return 'muted';
            }

            return str_contains($action, 'site_down') || str_contains($action, 'disk_low') ? 'danger' : 'warning';
        }

        if (str_starts_with($action, 'sugestao.') || str_contains($action, 'ip_restriction') || str_contains($action, 'ips.')) {
            return 'info';
        }

        if (str_contains($action, 'destroy') || str_contains($action, 'removid') || str_contains($action, 'rejeit')) {
            return 'warning';
        }

        return 'muted';
    }

    /**
     * Eventos automáticos de altíssima frequência (sync de cliente, healthcheck,
     * backup bem-sucedido) que dominam a lista visualmente sem serem, na
     * prática, algo que alguém precisa ler um por um -- ocultados por padrão
     * na tela de auditoria pra não afogar as ações reais (login, edição,
     * remoção) no meio deles.
     */
    public static function isRotina(string $action): bool
    {
        return in_array($action, ['health.ok', 'rpz.endpoint.downloaded', 'backup.r2_uploaded'], true);
    }

    public static function bucketLabel(string $bucket): string
    {
        return match ($bucket) {
            'danger' => 'Alto',
            'warning' => 'Médio',
            'info' => 'Info',
            default => 'Baixo',
        };
    }

    /**
     * Categorias pra agrupar/filtrar as dezenas de `action` distintas por
     * área do sistema, em vez de deixar o usuário adivinhar palavras-chave.
     *
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            'anatel' => 'ANATEL',
            'auth' => 'Autenticação e segurança',
            'backup' => 'Backup',
            'configuracoes' => 'Configurações',
            'empresa' => 'Empresas',
            'licenca' => 'Licenças',
            'lista' => 'Listas e fontes',
            'dominio' => 'Domínios',
            'endpoint' => 'RPZ',
            'rbl' => 'RBL Checker',
            'servidor' => 'Servidores',
            'sugestao' => 'Sugestões',
            'health' => 'Sistema (healthcheck)',
            'user' => 'Usuários',
        ];
    }

    public static function category(string $action): string
    {
        $prefix = strtolower(strstr($action, '.', true) ?: $action);

        return match ($prefix) {
            'security' => 'auth',
            'mikrotik', 'rpz' => 'endpoint',
            default => array_key_exists($prefix, self::categories()) ? $prefix : 'outros',
        };
    }

    public static function categoryLabel(string $action): string
    {
        return self::categories()[self::category($action)] ?? 'Outros';
    }

    /**
     * @return list<string> prefixos de `action` (antes do primeiro ponto) que
     *                       pertencem à categoria informada, pra montar o
     *                       filtro por categoria como WHERE no banco.
     */
    public static function prefixesForCategory(string $categoryKey): array
    {
        $prefixes = ['anatel', 'auth', 'security', 'backup', 'configuracoes', 'empresa', 'licenca', 'lista', 'dominio', 'mikrotik', 'rpz', 'rbl', 'servidor', 'sugestao', 'health', 'user'];

        return array_values(array_filter($prefixes, fn ($prefix) => self::category($prefix) === $categoryKey));
    }

    /**
     * Nome amigável do "alvo" da ação (target_type é uma chave curta como
     * "lista"/"anatel_exclusion", não o FQCN da classe).
     */
    public static function targetLabel(?string $targetType, ?int $targetId): ?string
    {
        if ($targetType === null) {
            return null;
        }

        $label = match ($targetType) {
            'lista' => 'Lista',
            'empresa' => 'Empresa',
            'licenca' => 'Licença',
            'servidor' => 'Servidor',
            'sugestao' => 'Sugestão',
            'user' => 'Usuário',
            'anatel_exclusion' => 'Exclusão ANATEL',
            'anatel_import' => 'Importação ANATEL',
            'rbl_event' => 'Evento RBL',
            'security_ban' => 'Bloqueio de segurança',
            default => ucfirst(str_replace('_', ' ', $targetType)),
        };

        return $targetId !== null ? "{$label} #{$targetId}" : $label;
    }

    public function actorLabel(): string
    {
        if ($this->user !== null) {
            return $this->user->name;
        }
        if (in_array($this->action, ['rpz.endpoint.downloaded', 'mikrotik.endpoint.downloaded'], true)) {
            return 'Servidor DNS';
        }
        if (str_starts_with($this->action, 'health.')) {
            return 'Sistema (automático)';
        }
        return 'Sem usuário identificado';
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            // ANATEL
            'anatel.batch.published' => 'Lote ANATEL publicado no RPZ',
            'anatel.batch.rejected' => 'Lote ANATEL rejeitado',
            'anatel.batch.preview_ready' => 'Lote ANATEL com prévia pronta pra aprovação',
            'anatel.exclusion.created' => 'Exclusão ANATEL criada',
            'anatel.exclusion.updated' => 'Exclusão ANATEL reativada',
            'anatel.exclusion.disabled' => 'Exclusão ANATEL desativada',
            'anatel.exclusion.edited' => 'Exclusão ANATEL editada',
            'anatel.exclusion.deleted' => 'Exclusão ANATEL removida',
            'anatel.import.started' => 'Importação de PDF/planilha ANATEL iniciada',
            'anatel.import.completed' => 'Importação de PDF/planilha ANATEL concluída',
            'anatel.import.preview_ready' => 'Prévia da importação ANATEL pronta pra aprovação',
            'anatel.import.blocked' => 'Importação ANATEL bloqueada (extração vazia/incoerente)',
            'anatel.import.failed' => 'Importação ANATEL falhou',
            'anatel.import.rejected' => 'Importação ANATEL rejeitada',
            'anatel.import.deleted' => 'Importação ANATEL removida',
            'anatel.legacy_exclude_imported' => 'Lista antiga de exclusões ANATEL importada',
            'anatel.legacy_feed.synced' => 'Feed legado ANATEL sincronizado',
            'anatel.legacy_feed.failed' => 'Falha ao sincronizar feed legado ANATEL',

            // Autenticação e segurança
            'auth.login' => 'Login realizado',
            'auth.login_failed' => 'Falha de login',
            'auth.logout' => 'Sessão encerrada',
            'auth.password_changed' => 'Senha alterada pelo próprio usuário',
            'security.ip_banned' => 'IP bloqueado pelo fail2ban',
            'security.ip_unbanned' => 'IP desbloqueado pelo fail2ban',

            // Backup
            'backup.manual_triggered' => 'Backup manual disparado',
            'backup.failed' => 'Backup falhou',
            'backup.r2_uploaded' => 'Backup enviado pro Cloudflare R2',
            'backup.r2_failed' => 'Falha ao enviar backup pro Cloudflare R2',
            'backup.r2_pruned' => 'Backups antigos removidos do R2',

            // Configurações
            'configuracoes.r2_atualizado' => 'Configuração de backup (R2) atualizada',
            'configuracoes.telegram_atualizado' => 'Configuração de notificação Telegram atualizada',
            'configuracoes.limpeza_servidores_atualizado' => 'Configuração de limpeza de servidores inativos atualizada',

            // Empresas
            'empresa.cadastro_publico' => 'Empresa cadastrada via formulário público',
            'empresa.created' => 'Empresa criada',
            'empresa.updated' => 'Empresa atualizada',
            'empresa.destroyed' => 'Empresa removida',

            // Licenças
            'licenca.created' => 'Licença criada',
            'licenca.updated' => 'Licença atualizada',
            'licenca.destroyed' => 'Licença removida',

            // Domínios
            'dominio.created' => 'Domínio adicionado ao bloqueio',
            'dominio.updated' => 'Domínio atualizado',
            'dominio.destroyed' => 'Domínio removido',

            // Listas e fontes
            'lista.created' => 'Lista criada',
            'lista.updated' => 'Lista atualizada',
            'lista.destroyed' => 'Lista removida',
            'lista.externa.sync' => 'Lista externa sincronizada',
            'lista.externa.sync_falhou' => 'Falha ao sincronizar lista externa',
            'lista.externa.sync_habilitado' => 'Sincronização automática da lista reativada',
            'lista.externa.sync_pausado' => 'Sincronização automática da lista pausada',

            // Endpoints (RPZ / Mikrotik)
            'mikrotik.endpoint.denied' => 'Acesso negado ao endpoint Mikrotik',
            'mikrotik.endpoint.downloaded' => 'Lista Mikrotik baixada por um servidor',
            'rpz.endpoint.acl_updated' => 'Lista de IPs autorizados do endpoint RPZ atualizada',
            'rpz.endpoint.denied' => 'Acesso negado ao endpoint RPZ',
            'rpz.endpoint.downloaded' => 'Servidor consultou a lista RPZ',
            'rpz.endpoint.slug_changed' => 'Identificador (slug) do endpoint RPZ alterado',

            // RBL Checker
            'rbl.delist.updated' => 'Solicitação de delist RBL atualizada',
            'rbl.delist.instructions_viewed' => 'Instruções de delist RBL visualizadas',
            'rbl.delist.requested' => 'Delist RBL solicitado ao provedor',
            'rbl.delist.accepted' => 'Delist RBL aceito',
            'rbl.delist.rejected' => 'Delist RBL rejeitado',
            'rbl.delist.not_applicable' => 'Delist RBL marcado como não aplicável',
            'rbl.event.marked_investigating' => 'Evento RBL marcado como em investigação',
            'rbl.event.marked_investigated' => 'Evento RBL marcado como investigado',
            'rbl.event.marked_false_positive' => 'Evento RBL marcado como falso positivo',
            'rbl.event.investigation_updated' => 'Investigação de evento RBL atualizada',

            // Servidores
            'servidor.lista_vinculada' => 'Lista vinculada ao servidor',
            'servidor.created' => 'Servidor criado',
            'servidor.updated' => 'Servidor atualizado',
            'servidor.destroyed' => 'Servidor removido',
            'servidor.ips.added' => 'IP(s) adicionado(s) à lista de acesso do servidor',
            'servidor.ips.removed' => 'IP(s) removido(s) da lista de acesso do servidor',
            'servidor.ip_restriction_enabled' => 'Restrição de IP do servidor ativada',
            'servidor.ip_restriction_disabled' => 'Restrição de IP do servidor desativada',
            'servidor.ip_pendente' => 'Cliente informou o IP do servidor: aguardando liberação',
            'servidor.ips.approved' => 'IP do servidor liberado pelo suporte',
            'servidor.inatividade_avisada' => 'Servidor avisado: sem consultar a zona além do prazo',
            'servidor.removido_inatividade' => 'Servidor removido automaticamente por inatividade',

            // Sugestões
            'sugestao.created' => 'Sugestão de domínio recebida',
            'sugestao.approved' => 'Sugestão de domínio aprovada',
            'sugestao.rejected' => 'Sugestão de domínio rejeitada',

            // Sistema (healthcheck)
            'health.ok' => 'Verificação do sistema: tudo normal',
            'health.disk_low' => 'Alerta: disco do servidor com pouco espaço',
            'health.cert_unreadable' => 'Alerta: certificado TLS ilegível',
            'health.cert_expiring' => 'Alerta: certificado TLS perto de expirar',
            'health.site_down' => 'Alerta: site fora do ar',

            // Usuários
            'user.created' => 'Usuário criado',
            'user.updated' => 'Usuário atualizado',
            'user.destroyed' => 'Usuário removido',
            'user.password_reset' => 'Senha de usuário redefinida por um admin',

            default => ucfirst(str_replace(['.', '_'], ' ', $action)),
        };
    }
}
