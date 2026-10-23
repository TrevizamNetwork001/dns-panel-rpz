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
        </div>

        <div class="form-actions">
            <a href="{{ route('empresas.index') }}" class="button button-secondary">Cancelar</a>
            <button type="submit" class="button button-primary">Salvar</button>
        </div>
    </form>
    </div>
@endsection
