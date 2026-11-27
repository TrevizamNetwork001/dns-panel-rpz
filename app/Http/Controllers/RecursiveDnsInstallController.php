<?php

namespace App\Http\Controllers;

use App\Models\RecursiveDnsServer;
use App\Services\RecursiveDnsDiagnostic;
use App\Services\RecursiveDnsInstaller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

class RecursiveDnsInstallController extends Controller
{
    public function preview(Request $request, RecursiveDnsServer $server, RecursiveDnsInstaller $installer)
    {
        abort_unless($server->canInstall(), 409, 'Diagnostique um Debian 13 sem Unbound e com a porta 53 livre.');

        $profile = $request->validate([
            'threads' => ['required', 'integer', 'min:1', 'max:'.(int) $server->diagnostic['cpu']],
            'message_cache' => ['required', 'integer', 'min:16', 'max:32768'],
            'record_cache' => ['required', 'integer', 'min:32', 'max:65536'],
        ]);
        if ($profile['message_cache'] + $profile['record_cache'] > intdiv((int) $server->diagnostic['ram_mib'], 2)) {
            return back()->withInput()->withErrors(['record_cache' => 'Reserve no máximo metade da RAM detectada para a soma dos caches.']);
        }

        $token = Str::random(48);
        $request->session()->put('recursive_dns_install', [
            'token' => $token,
            'server' => $server->id,
            'revision' => $this->revision($server),
            'expires' => now()->addMinutes(10)->timestamp,
            'profile' => $profile,
        ]);

        return view('dns-recursivo.install', [
            'server' => $server,
            'token' => $token,
            'configuration' => $installer->configuration($profile),
        ]);
    }

    public function apply(Request $request, RecursiveDnsServer $server, RecursiveDnsInstaller $installer, RecursiveDnsDiagnostic $diagnostic)
    {
        $redirect = redirect()->route('dns-recursivo.edit', $server);
        $plan = $request->session()->pull('recursive_dns_install');
        if (! $plan || $plan['server'] !== $server->id || $plan['expires'] <= now()->timestamp ||
            ! hash_equals($plan['token'], $request->string('plan')->toString()) ||
            ! hash_equals($plan['revision'], $this->revision($server)) || ! $server->canInstall()) {
            return $redirect->withErrors(['install' => 'A prévia expirou ou o cadastro mudou. Gere uma nova prévia.']);
        }

        $server->forceFill(['diagnostic' => null, 'diagnosed_at' => null])->save();

        try {
            $installer->install($server, $plan['profile']);
        } catch (ProcessTimedOutException) {
            return $redirect->withErrors(['install' => 'A instalação excedeu o tempo limite. Execute o diagnóstico para conferir o estado do servidor.']);
        } catch (RuntimeException $exception) {
            return $redirect->withErrors(['install' => get_class($exception) === RuntimeException::class
                ? $exception->getMessage()
                : 'Não foi possível executar a instalação via SSH.']);
        }

        try {
            $server->fill(['diagnostic' => $diagnostic->run($server), 'diagnosed_at' => now()])->save();
        } catch (RuntimeException) {
            return $redirect->with('dns_status', 'Unbound instalado e testado. Execute o diagnóstico para atualizar a ficha.');
        }

        return $redirect->with('dns_status', 'Unbound instalado. A consulta DNS pelo loopback foi validada.');
    }

    private function revision(RecursiveDnsServer $server): string
    {
        return hash('sha256', json_encode($server->getRawOriginal(), JSON_THROW_ON_ERROR));
    }
}
