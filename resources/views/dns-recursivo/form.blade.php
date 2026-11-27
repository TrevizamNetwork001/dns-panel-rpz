@extends('layouts.app')
@section('title', 'DNS Recursivo')

@section('content')
@include('dns-recursivo.styles')
<div class="recursive-dns">
    <div class="page-heading">
        <div class="dns-heading">
            <div class="dns-kicker">DNS RECURSIVO</div>
            <h1>{{ $server->exists ? $server->name : 'Novo servidor' }}</h1>
        </div>
        <a class="button" href="{{ route('dns-recursivo.index') }}">Servidores</a>
    </div>

    @if (session('dns_status'))
        <p role="status">{{ session('dns_status') }}</p>
    @endif
    @if ($errors->any())
        <ul role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="post" action="{{ $server->exists ? route('dns-recursivo.update', $server) : route('dns-recursivo.store') }}">
        @csrf
        @if ($server->exists)
            @method('PUT')
        @endif
        <section class="dns-section">
            <h2>Identificação</h2>
            <div class="dns-grid">
                <label class="dns-field">Nome <input name="name" required maxlength="120" value="{{ old('name', $server->name) }}"></label>
                <label class="dns-field">Hostname <input name="hostname" required maxlength="253" value="{{ old('hostname', $server->hostname) }}"></label>
                <label class="dns-field">Cliente RPZ
                    <select name="empresa_id">
                        <option value="">Sem vínculo</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected(old('empresa_id', $server->empresa_id) == $company->id)>{{ $company->nome }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </section>
        <section class="dns-section">
            <h2>Acesso de gerenciamento</h2>
            <div class="dns-grid">
                <label class="dns-field">IPv4 <input name="ipv4" value="{{ old('ipv4', $server->ipv4) }}"></label>
                <label class="dns-field">IPv6 <input name="ipv6" value="{{ old('ipv6', $server->ipv6) }}"></label>
                <label class="dns-field">Endereço usado pelo SSH
                    <select name="ssh_family">
                        <option value="4" @selected(old('ssh_family', $server->ssh_family ?? 4) == 4)>IPv4</option>
                        <option value="6" @selected(old('ssh_family', $server->ssh_family) == 6)>IPv6</option>
                    </select>
                </label>
                <label class="dns-field">Usuário SSH <input name="ssh_user" required value="{{ old('ssh_user', $server->ssh_user) }}"></label>
                <label class="dns-field">Porta SSH <input type="number" name="ssh_port" min="1" max="65535" required value="{{ old('ssh_port', $server->ssh_port ?? 22) }}"></label>
                <label class="dns-field">Senha SSH <input type="password" name="password" autocomplete="new-password" @required(!$server->exists)>
                    @if ($server->exists)<small>Deixe em branco para manter a senha salva.</small>@endif
                </label>
                <label class="dns-field">Impressão digital da chave do host
                    <input name="ssh_fingerprint" placeholder="SHA256:…" value="{{ old('ssh_fingerprint', $server->ssh_fingerprint) }}">
                    <small>Opcional ao cadastrar. Para confirmar, cole a saída de <code>ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub</code> executado no servidor.</small>
                </label>
            </div>
            <button class="button dns-primary" type="submit">Salvar cadastro</button>
        </section>
    </form>

    @if ($server->exists)
        <section class="dns-section dns-status">
            <h2>Diagnóstico SSH</h2>
            <form method="post" action="{{ route('dns-recursivo.host-keys', $server) }}">
                @csrf
                <button type="submit" class="button">Consultar chaves SSH</button>
            </form>
            @if (session('dns_host_keys.server') === $server->id)
                <p>Compare uma das chaves com a saída do comando na console do servidor. Cole a chave correspondente no campo acima e salve.</p>
                <ul>
                    @foreach (session('dns_host_keys.keys', []) as $fingerprint)
                        <li><code>{{ $fingerprint }}</code></li>
                    @endforeach
                </ul>
            @endif
            @if ($server->diagnostic)
                @php($diagnostic = $server->diagnostic)
                <dl class="dns-grid">
                    <div><dt>Servidor remoto</dt><dd>{{ $diagnostic['hostname'] }}</dd></div>
                    <div><dt>Sistema</dt><dd>{{ $diagnostic['system'] ?? '—' }}</dd></div>
                    <div><dt>CPU</dt><dd>{{ $diagnostic['cpu'] ?? '—' }}</dd></div>
                    <div><dt>RAM</dt><dd>{{ isset($diagnostic['ram_mib']) ? $diagnostic['ram_mib'].' MiB' : '—' }}</dd></div>
                    <div><dt>Unbound</dt><dd>{{ $diagnostic['active'] === '0' ? 'Ativo' : ($diagnostic['unbound'] === '0' ? 'Instalado, inativo' : 'Não detectado') }}</dd></div>
                    <div><dt>Interfaces</dt><dd>{{ $diagnostic['interfaces'] ?? '—' }}</dd></div>
                    <div><dt>Sockets na porta 53</dt><dd>{{ $diagnostic['port_53'] ?? '—' }}</dd></div>
                </dl>
                @isset($diagnostic['recommended'])
                    <h2>Perfil recomendado</h2>
                    <p>{{ $diagnostic['recommended']['threads'] }} threads · {{ $diagnostic['recommended']['message_cache'] }} MiB para mensagens · {{ $diagnostic['recommended']['record_cache'] }} MiB para registros</p>
                @endisset
                <p>Última verificação em {{ $server->diagnosed_at->format('d/m/Y H:i') }}.</p>
            @endif
            <form method="post" action="{{ route('dns-recursivo.diagnose', $server) }}">
                @csrf
                <button type="submit" class="button">Conectar e diagnosticar</button>
            </form>
        </section>
        @if ($server->canInstall())
            <section class="dns-section">
                <h2>Instalar Unbound</h2>
                <form method="post" action="{{ route('dns-recursivo.install.preview', $server) }}">
                    @csrf
                    @php($profile = $server->diagnostic['recommended'])
                    <div class="dns-grid">
                        <label class="dns-field">Threads <input type="number" name="threads" min="1" max="{{ $server->diagnostic['cpu'] }}" required value="{{ old('threads', $profile['threads']) }}"></label>
                        <label class="dns-field">Cache de mensagens (MiB) <input type="number" name="message_cache" min="16" required value="{{ old('message_cache', $profile['message_cache']) }}"></label>
                        <label class="dns-field">Cache de registros (MiB) <input type="number" name="record_cache" min="32" required value="{{ old('record_cache', $profile['record_cache']) }}"></label>
                    </div>
                    <button type="submit" class="button dns-primary">Revisar instalação</button>
                </form>
            </section>
        @endif
    @endif
</div>
@endsection
