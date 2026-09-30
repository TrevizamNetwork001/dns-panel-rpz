<?php

namespace App\Console\Commands;

use App\Services\RpzPrimaryResponder;
use Illuminate\Console\Command;

/**
 * Servico UDP dedicado do "primary" nativo do RPZ (RPZ-UNBOUND-LAB-1, fase
 * de producao real). Roda continuamente (container proprio no compose.yml,
 * fora do ciclo de request HTTP do PHP-FPM) respondendo sondagens SOA-only
 * dos Unbound de cliente. Ver RpzPrimaryResponder pra logica de resposta.
 */
class RpzPrimaryServe extends Command
{
    protected $signature = 'rpz:primary-serve';

    protected $description = 'Sobe o servico UDP que responde sondagens SOA-only do "primary" RPZ (roda para sempre)';

    public function handle(RpzPrimaryResponder $responder): int
    {
        if (! config('rpz.primary_enabled')) {
            $this->warn('RPZ_PRIMARY_ENABLED=false -- servico nao iniciado. Ative a flag pra rodar de verdade.');

            return self::SUCCESS;
        }

        $bind = (string) config('rpz.primary_bind');
        $port = (int) config('rpz.primary_port');

        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($socket === false) {
            $this->error('Nao foi possivel criar o socket UDP: '.socket_strerror(socket_last_error()));

            return self::FAILURE;
        }

        if (! @socket_bind($socket, $bind, $port)) {
            $this->error("Nao foi possivel fazer bind em {$bind}:{$port}: ".socket_strerror(socket_last_error($socket)));

            return self::FAILURE;
        }

        $this->info("rpz:primary-serve ouvindo em {$bind}:{$port} (UDP)...");

        $rodando = true;
        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            $parar = function () use (&$rodando): void { $rodando = false; };
            pcntl_signal(SIGTERM, $parar);
            pcntl_signal(SIGINT, $parar);
        }

        while ($rodando) {
            $sourceIp = '';
            $sourcePort = 0;
            $packet = @socket_recvfrom($socket, $buffer, 512, 0, $sourceIp, $sourcePort);

            if ($packet === false) {
                $erro = socket_last_error($socket);
                socket_clear_error($socket);
                // EINTR (4) acontece ao receber um sinal (ex: SIGTERM) -- nao
                // e uma falha real, so sai do loop na proxima checagem de
                // $rodando.
                if ($erro !== 4) {
                    $this->warn("Erro no recvfrom: {$erro}");
                }

                continue;
            }

            try {
                $resposta = $responder->respond($buffer, $sourceIp);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            if ($resposta !== null) {
                @socket_sendto($socket, $resposta, strlen($resposta), 0, $sourceIp, $sourcePort);
            }
        }

        socket_close($socket);
        $this->info('rpz:primary-serve encerrado.');

        return self::SUCCESS;
    }
}
