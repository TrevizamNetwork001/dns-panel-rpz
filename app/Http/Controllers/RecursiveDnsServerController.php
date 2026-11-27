<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\RecursiveDnsServer;
use App\Services\RecursiveDnsDiagnostic;
use App\Services\RecursiveDnsSsh;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

class RecursiveDnsServerController extends Controller
{
    public function index()
    {
        return view('dns-recursivo.index', [
            'servers' => RecursiveDnsServer::with('empresa:id,nome')->orderBy('name')->paginate(25),
        ]);
    }

    public function create()
    {
        return $this->form(new RecursiveDnsServer);
    }

    public function edit(RecursiveDnsServer $server)
    {
        return $this->form($server);
    }

    public function store(Request $request)
    {
        $server = RecursiveDnsServer::create($this->validated($request));

        return redirect()->route('dns-recursivo.edit', $server)->with('dns_status', 'Servidor cadastrado.');
    }

    public function update(Request $request, RecursiveDnsServer $server)
    {
        $server->fill($this->validated($request, $server));
        if ($server->isDirty(['ipv4', 'ipv6', 'ssh_family', 'ssh_port', 'ssh_user', 'ssh_fingerprint', 'ssh_password'])) {
            $server->diagnostic = null;
            $server->diagnosed_at = null;
        }
        $server->save();

        return back()->with('dns_status', 'Cadastro atualizado.');
    }

    public function diagnose(RecursiveDnsServer $server, RecursiveDnsDiagnostic $diagnostic)
    {
        try {
            $result = $diagnostic->run($server);
        } catch (ProcessTimedOutException) {
            return back()->withErrors(['diagnostic' => 'Tempo limite da conexão ou do diagnóstico SSH.']);
        } catch (RuntimeException $exception) {
            $message = get_class($exception) === RuntimeException::class
                ? $exception->getMessage()
                : 'Não foi possível iniciar o cliente SSH no painel.';

            return back()->withErrors(['diagnostic' => $message]);
        }

        $server->fill(['diagnostic' => $result, 'diagnosed_at' => now()])->save();

        return back()->with('dns_status', 'Diagnóstico concluído.');
    }

    public function hostKeys(RecursiveDnsServer $server, RecursiveDnsSsh $ssh)
    {
        $redirect = redirect()->route('dns-recursivo.edit', $server);
        try {
            $keys = $ssh->fingerprints($server);
        } catch (ProcessTimedOutException) {
            return $redirect->withErrors(['ssh_fingerprint' => 'O servidor excedeu o tempo limite para mostrar a chave SSH.']);
        } catch (RuntimeException $exception) {
            return $redirect->withErrors(['ssh_fingerprint' => get_class($exception) === RuntimeException::class
                ? $exception->getMessage()
                : 'Não foi possível consultar as chaves SSH no painel.']);
        }

        return $redirect->with('dns_host_keys', ['server' => $server->id, 'keys' => $keys]);
    }

    private function form(RecursiveDnsServer $server)
    {
        return view('dns-recursivo.form', [
            'server' => $server,
            'companies' => Empresa::orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    private function validated(Request $request, ?RecursiveDnsServer $server = null): array
    {
        $fingerprint = $request->input('ssh_fingerprint');
        if (is_string($fingerprint) && strlen($fingerprint) <= 1024 &&
            preg_match('/(?:^|\s)(SHA256:[A-Za-z0-9+\/]{43})(?=\s|$)/', $fingerprint, $match)) {
            $request->merge(['ssh_fingerprint' => $match[1]]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'hostname' => ['required', 'string', 'max:253', 'regex:/\A[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\z/i'],
            'empresa_id' => ['nullable', 'integer', 'exists:empresas,id'],
            'ipv4' => ['nullable', 'required_if:ssh_family,4', 'ipv4'],
            'ipv6' => ['nullable', 'required_if:ssh_family,6', 'ipv6', 'max:45'],
            'ssh_family' => ['required', Rule::in([4, 6])],
            'ssh_port' => ['required', 'integer', 'between:1,65535'],
            'ssh_user' => ['required', 'string', 'max:64', 'regex:/\A[a-z0-9_][a-z0-9_.-]*\z/i'],
            'ssh_fingerprint' => ['nullable', 'string', 'regex:/\ASHA256:[a-zA-Z0-9+\/]{43}\z/'],
            'password' => [$server ? 'nullable' : 'required', 'string', 'max:1024', 'regex:/\A[^\r\n\x00]*\z/'],
        ], [
            'ssh_fingerprint.regex' => 'Cole a impressão digital SHA256 ou a linha inteira do ssh-keygen.',
        ]);

        $data['ssh_fingerprint'] = $data['ssh_fingerprint'] ?? ($server?->ssh_fingerprint ?? '');
        if ($request->has('ssh_fingerprint') && $request->input('ssh_fingerprint') === null) {
            $data['ssh_fingerprint'] = '';
        }

        if (isset($data['password'])) {
            $data['ssh_password'] = $data['password'];
        }
        unset($data['password']);

        return $data;
    }
}
