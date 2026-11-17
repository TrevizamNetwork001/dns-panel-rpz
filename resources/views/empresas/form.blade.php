@extends('layouts.app')

@section('title', $empresa->exists ? 'Editar empresa' : 'Nova empresa')

@section('content')
    @include('empresas.partials.admin-form-styles')
    <div class="admin-empresa-form">
    <nav class="empresa-form-breadcrumb" aria-label="Navegação"><a href="{{ route('empresas.index') }}">Empresas</a><x-ui-icon name="chevron" size="14" /><span>{{ $empresa->exists ? $empresa->nome : 'Nova empresa' }}</span></nav>
    <div class="page-heading page-heading-compact">
        <div>
            <div class="page-eyebrow">Gestão de empresas</div>
            <h1>{{ $empresa->exists ? 'Editar empresa' : 'Nova empresa' }}</h1>
            @if ($empresa->exists)<p>{{ $empresa->nome }}</p>@endif
        </div>
        @if ($empresa->exists)<a href="{{ route('empresas.show', $empresa) }}" class="button button-secondary"><x-ui-icon name="building" size="17" />Ver empresa</a>@endif
    </div>

    <form action="{{ $empresa->exists ? route('empresas.update', $empresa) : route('empresas.store') }}" method="POST">
        @csrf
        @if ($empresa->exists)
            @method('PUT')
        @endif

        <div class="panel form-panel" style="margin-bottom:14px">
            <div class="panel-header"><h2><x-ui-icon name="building" size="23" />Identificação</h2></div>
            <div class="form-grid">
                <div class="field-group">
                    <label for="nome">Nome</label>
                    <input class="form-control" type="text" id="nome" name="nome" value="{{ old('nome', $empresa->nome) }}" required>
                </div>

                <div class="field-group">
                    <label for="nome_fantasia">Nome fantasia</label>
                    <input class="form-control" type="text" id="nome_fantasia" name="nome_fantasia" value="{{ old('nome_fantasia', $empresa->nome_fantasia) }}" maxlength="255">
                </div>

                <div class="field-group">
                    <label for="documento">Documento (CNPJ)</label>
                    <input class="form-control" type="text" id="documento" name="documento" value="{{ old('documento', $empresa->documento) }}">
                </div>

                <div class="field-group">
                    <label for="telefone">Telefone</label>
                    <input class="form-control" type="tel" id="telefone" name="telefone" value="{{ old('telefone', $empresa->telefone) }}" maxlength="32" autocomplete="tel" placeholder="(11) 99999-9999">
                </div>

                <div class="field-group">
                    <label for="email_contato">E-mail de contato</label>
                    <input class="form-control" type="email" id="email_contato" name="email_contato" value="{{ old('email_contato', $empresa->email_contato) }}">
                </div>

                <div class="empresa-extra-contatos">
                    <button type="button" class="button button-secondary" id="empresa-contatos-open" aria-haspopup="dialog" aria-controls="empresa-contatos">
                        <x-ui-icon name="settings" size="16" />
                        Gerenciar contatos
                    </button>
                </div>
                <dialog class="empresa-contatos-drawer" id="empresa-contatos" aria-labelledby="empresa-contatos-title" @if ($errors->has('emails_adicionais')) data-validation-error @endif>
                    <div class="panel-header">
                        <h2 id="empresa-contatos-title">Contatos adicionais</h2>
                        <button type="button" class="button button-secondary" data-empresa-contatos-close aria-label="Fechar contatos adicionais">
                            <x-ui-icon name="x" size="18" />
                        </button>
                    </div>
                    <div class="field-group">
                        <label for="emails_adicionais">E-mails adicionais</label>
                        <textarea class="form-control" id="emails_adicionais" name="emails_adicionais" rows="3" maxlength="2600" spellcheck="false" aria-describedby="emails-adicionais-help">{{ old('emails_adicionais', implode("\n", $empresa->emails_adicionais ?? [])) }}</textarea>
                        <small id="emails-adicionais-help">Até 10 e-mails, um por linha. Esses contatos também recebem a confirmação de aprovação da Anatel.</small>
                        @error('emails_adicionais')<small role="alert">{{ $message }}</small>@enderror
                    </div>
                    <div class="empresa-contatos-actions">
                        <small>As alterações são gravadas ao salvar a empresa.</small>
                        <button type="button" class="button button-primary" data-empresa-contatos-close>Concluir</button>
                    </div>
                </dialog>

                <div class="field-group">
                    <label for="status">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="active" @selected(old('status', $empresa->status) === 'active')>Ativa</option>
                        <option value="inactive" @selected(old('status', $empresa->status) === 'inactive')>Inativa</option>
                    </select>
                </div>
                <div class="field-group">
                    <label for="rpz_bloqueio_modo">Bloqueio na URL RPZ da empresa</label>
                    <select class="form-control" id="rpz_bloqueio_modo" name="rpz_bloqueio_modo">
                        <option value="nxdomain" @selected(old('rpz_bloqueio_modo', $empresa->rpz_bloqueio_modo ?? 'nxdomain') === 'nxdomain')>NXDOMAIN (padrão)</option>
                        <option value="redirect" @selected(old('rpz_bloqueio_modo', $empresa->rpz_bloqueio_modo) === 'redirect')>Página de bloqueio</option>
                    </select>
                    <small>Vale para todos os DNS que usam a URL desta empresa. A página funciona em HTTP; sites HTTPS podem apresentar erro de certificado.</small>
                </div>
            </div>

        <div class="form-actions">
            <a href="{{ route('empresas.index') }}" class="button button-secondary">Cancelar</a>
            <button type="submit" class="button button-primary">Salvar</button>
        </div>
        </div>
    </form>
    </div>
    <script>
        (() => {
            const dialog = document.getElementById('empresa-contatos');
            const trigger = document.getElementById('empresa-contatos-open');

            trigger.addEventListener('click', () => dialog.showModal());
            dialog.querySelectorAll('[data-empresa-contatos-close]').forEach((button) => {
                button.addEventListener('click', () => dialog.close());
            });
            if (dialog.hasAttribute('data-validation-error')) {
                dialog.showModal();
            }
        })();
    </script>
@endsection
