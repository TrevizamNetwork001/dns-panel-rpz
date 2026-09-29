<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Servidor;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;

class ServidoresInativosCleaner
{
    public const DIAS_PADRAO = 60;

    public const AVISO_DIAS_PADRAO = 7;

    public function __construct(private TelegramNotifier $telegram, private RpzDirtyMarker $rpzDirty) {}

    /**
     * @return array{ativo: bool, dias: int, aviso_dias: int}
     */
    public function config(): array
    {
        return [
            'ativo' => Setting::get('servidor_limpeza_ativo', '0') === '1',
            'dias' => (int) (Setting::get('servidor_limpeza_dias') ?: self::DIAS_PADRAO),
            'aviso_dias' => (int) (Setting::get('servidor_limpeza_aviso_dias') ?: self::AVISO_DIAS_PADRAO),
        ];
    }

    /**
     * Servidores sem consulta à zona há mais de $dias. Servidor que nunca
     * consultou é medido pela data de criação.
     */
    public function inativosQuery(?int $dias = null): Builder
    {
        $limite = now()->subDays($dias ?? $this->config()['dias']);

        return Servidor::query()->where(function (Builder $query) use ($limite) {
            $query->where('last_synced_at', '<', $limite)
                ->orWhere(fn (Builder $sem) => $sem->whereNull('last_synced_at')->where('created_at', '<', $limite));
        });
    }

    /**
     * Fluxo em duas etapas: primeiro avisa (Telegram + auditoria), e só
     * remove quando o prazo de aviso passou e o servidor continua parado.
     * Servidor que voltou a consultar tem o aviso cancelado.
     *
     * @return array{status: string, avisados: list<array{servidor: string, empresa: string, dias: int}>, removidos: list<array{servidor: string, empresa: string, dias: int}>, recuperados: int}
     */
    public function run(bool $dryRun = false): array
    {
        $config = $this->config();
        $resultado = ['status' => 'ok', 'avisados' => [], 'removidos' => [], 'recuperados' => 0];

        if (! $config['ativo'] && ! $dryRun) {
            $resultado['status'] = 'desativada';

            return $resultado;
        }

        $recuperar = Servidor::query()
            ->whereNotNull('inatividade_avisada_em')
            ->whereNotIn('id', $this->inativosQuery($config['dias'])->select('servidores.id'));
        $resultado['recuperados'] = (clone $recuperar)->count();
        if (! $dryRun && $resultado['recuperados'] > 0) {
            $recuperar->update(['inatividade_avisada_em' => null]);
        }

        $prazoAviso = now()->subDays($config['aviso_dias']);

        foreach ($this->inativosQuery($config['dias'])->with('empresa')->get() as $servidor) {
            $referencia = $servidor->last_synced_at ?? $servidor->created_at;
            $item = [
                'servidor' => $servidor->nome,
                'empresa' => $servidor->empresa->nome ?? '-',
                'dias' => (int) $referencia->diffInDays(now()),
            ];

            if ($servidor->inatividade_avisada_em === null) {
                $resultado['avisados'][] = $item;
                if (! $dryRun) {
                    $servidor->forceFill(['inatividade_avisada_em' => now()])->saveQuietly();
                    $this->audit('servidor.inatividade_avisada', $servidor, "Servidor \"{$servidor->nome}\" sem consultas há {$item['dias']} dia(s); será removido em {$config['aviso_dias']} dia(s) se não voltar");
                }

                continue;
            }

            if ($servidor->inatividade_avisada_em->lte($prazoAviso)) {
                $resultado['removidos'][] = $item;
                if (! $dryRun) {
                    $this->audit('servidor.removido_inatividade', $servidor, "Servidor \"{$servidor->nome}\" removido: {$item['dias']} dia(s) sem consultar a zona");
                    $this->rpzDirty->markForServer($servidor->id);
                    $servidor->delete();
                }
            }
        }

        if (! $dryRun && ($resultado['avisados'] !== [] || $resultado['removidos'] !== [])) {
            $this->telegram->notifyServidoresInativos($resultado['avisados'], $resultado['removidos'], $config['aviso_dias']);
        }

        return $resultado;
    }

    private function audit(string $action, Servidor $servidor, string $description): void
    {
        AuditLog::create([
            'user_id' => null,
            'empresa_id' => $servidor->empresa_id,
            'action' => $action,
            'target_type' => 'servidor',
            'target_id' => $servidor->id,
            'description' => $description,
            'ip_address' => null,
            'created_at' => now(),
        ]);
    }
}
