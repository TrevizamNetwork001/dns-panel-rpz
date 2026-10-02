@extends('layouts.app')

@section('title', $licenca->exists ? 'Editar licença' : 'Nova licença')

@section('content')
    @php $validityType = old('validity_type', $licenca->exists && $licenca->expires_at === null ? 'no_expiry' : 'with_expiry'); @endphp
    <style>
        .admin-license-form { max-width: 1120px; margin: 0 auto; }
        .license-form-breadcrumb { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; color: var(--text-soft); font-size: 12px; }
        .license-form-breadcrumb a { color: var(--cyan); }
        .admin-license-form .page-heading { align-items: center; gap: 18px; margin-bottom: 26px; }
        .admin-license-form .page-eyebrow { color: var(--cyan); font-size: 11px; letter-spacing: .14em; }
        .admin-license-form h1 { margin: 8px 0; font-size: 32px; letter-spacing: -.035em; }
        .admin-license-form .page-heading p { margin: 0; color: var(--text-soft); font-size: 13px; }
        .admin-license-form > form.license-form-shell { display: grid; grid-template-columns: minmax(0, 1fr); gap: 0; align-items: start; padding: 26px 32px 18px; border: 1px solid var(--border); border-radius: 14px; background: var(--surface); box-shadow: none; }
        .admin-license-form .form-panel { min-width: 0; box-sizing: border-box; padding: 0 0 22px; border: 0; border-bottom: 1px solid var(--border-soft); border-radius: 0; background: transparent; box-shadow: none; }
        .admin-license-form .license-capacity-panel { padding-top: 22px; border-bottom: 0; }
        .admin-license-form .panel-header { display: block; padding-bottom: 12px; margin-bottom: 14px; border: 0; }
        .admin-license-form .panel-header h2 { display: flex; align-items: center; gap: 12px; margin: 0; font-size: 15px; }
        .admin-license-form .panel-header h2 > svg { color: var(--cyan); flex-shrink: 0; }
        .admin-license-form .panel-header p { margin: 5px 0 0 35px; color: var(--text-soft); font-size: 12px; }
        .admin-license-form .form-grid { gap: 14px 18px; }
        .admin-license-form .field-group { min-width: 0; }
        .admin-license-form .field-group label { font-size: 12px; font-weight: 550; }
        .admin-license-form .form-control { min-height: 46px; padding: 12px 14px; border: 1px solid var(--border); border-radius: 9px; color: var(--text); background: var(--surface-soft); font-size: 13px; }
        .admin-license-form .form-control:focus { border-color: var(--cyan); box-shadow: 0 0 0 3px var(--cyan-soft); }
        .admin-license-form select option { background: var(--surface); color: var(--text); }
        .admin-license-form .checkbox-label { cursor: pointer; gap: 10px; padding: 12px; border: 1px solid var(--border); border-radius: 9px; background: var(--surface-soft); }
        .admin-license-form .checkbox-label input { accent-color: var(--green); }
        .admin-license-form > form > .form-actions { grid-column: 1 / -1; justify-content: flex-end; flex-wrap: wrap; padding: 0; margin: 18px 0 0; border: 0; background: transparent; box-shadow: none; }
        .admin-license-form .form-actions .button { min-height: 42px; padding: 0 20px; font-size: 12px; }
        html:not([data-theme="light"]) .admin-license-form { --surface: #061b29; --surface-soft: #0b2130; --border: #234151; --border-soft: #173444; --text-soft: #adc5d9; --cyan: #00cbee; --green: #00dba0; }
        @media (max-width: 700px) {
            .admin-license-form .page-heading { align-items: flex-start; flex-direction: column; }
            .admin-license-form h1 { font-size: 27px; }
            .admin-license-form > form.license-form-shell { padding: 20px 18px 14px; }
            .admin-license-form .form-grid { grid-template-columns: minmax(0, 1fr); }
            .admin-license-form .form-panel { padding: 15px 16px; }
            .admin-license-form > form > .form-actions { margin-top: 16px; }
        }
    </style>
    <div class="admin-license-form">
        <nav class="license-form-breadcrumb" aria-label="Navegação"><a href="{{ route('licencas.index') }}">Licenças</a><x-ui-icon name="chevron" size="14" /><span>{{ $licenca->exists ? 'Editar licença' : 'Nova licença' }}</span></nav>
        <div class="page-heading page-heading-compact">
            <div>
                <div class="page-eyebrow">Gestão de licenças</div>
                <h1>{{ $licenca->exists ? 'Editar licença' : 'Nova licença' }}</h1>
                <p>{{ $licenca->exists ? 'Atualize os limites e a validade desta licença.' : 'Defina a capacidade de endpoints e o período de uso.' }}</p>
            </div>
        </div>

        <form class="license-form-shell" action="{{ $licenca->exists ? route('licencas.update', $licenca) : route('licencas.store') }}" method="POST">
            @csrf
            @if ($licenca->exists)
                @method('PUT')
            @endif

            <div class="panel form-panel license-company-panel">
                <div class="panel-header"><h2><x-ui-icon name="building" size="23" /> Empresa</h2><p>Empresa proprietária desta licença.</p></div>
                <div class="form-grid">
                    <div class="field-group">
                        <label for="empresa_id">Empresa licenciada</label>
                        @if ($licenca->exists)
                            <div class="form-control" aria-readonly="true">{{ $licenca->empresa->nome }}</div>
                        @else
                            <select class="form-control" id="empresa_id" name="empresa_id" required>
                                <option value="">-- selecione --</option>
                                @foreach ($empresas as $empresa)
                                    <option value="{{ $empresa->id }}" @selected(old('empresa_id', $licenca->empresa_id) == $empresa->id)>{{ $empresa->nome }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                </div>
            </div>

            <div class="panel form-panel license-validity-panel">
                <div class="panel-header"><h2><x-ui-icon name="clock" size="23" /> Validade</h2><p>Defina o período de validade da licença.</p></div>
                <div class="form-grid">
                    <div class="field-group">
                        <label for="starts_at">Início</label>
                        <input class="form-control" type="date" id="starts_at" name="starts_at" value="{{ old('starts_at', optional($licenca->starts_at)->format('Y-m-d')) }}" required>
                    </div>

                    <div class="field-group" id="expires-at-group">
                        <label for="expires_at">Expiração</label>
                        <input class="form-control" type="date" id="expires_at" name="expires_at" value="{{ old('expires_at', optional($licenca->expires_at)->format('Y-m-d')) }}">
                    </div>

                    <div class="field-group field-span-2">
                        <label>Tipo de validade</label>
                        <label class="checkbox-label"><input type="radio" name="validity_type" value="with_expiry" @checked($validityType === 'with_expiry')> <span>Com vencimento</span></label>
                        <label class="checkbox-label"><input type="radio" name="validity_type" value="no_expiry" @checked($validityType === 'no_expiry')> <span>Sem vencimento</span></label>
                    </div>
                </div>
            </div>

            <div class="panel form-panel license-capacity-panel">
                <div class="panel-header"><h2><x-ui-icon name="settings" size="23" /> Capacidade e status</h2><p>Configure o limite de endpoints e o status da licença.</p></div>
                <div class="form-grid">
                    <div class="field-group">
                        <label for="max_servidores">Máximo de Endpoints RPZ</label>
                        <input class="form-control" type="number" id="max_servidores" name="max_servidores" min="1" value="{{ old('max_servidores', $licenca->max_servidores ?? 1) }}" required>
                    </div>

                    <div class="field-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="active" @selected(old('status', $licenca->status) === 'active')>Ativa</option>
                            <option value="inactive" @selected(old('status', $licenca->status) === 'inactive')>Inativa</option>
                            <option value="expired" @selected(old('status', $licenca->status) === 'expired')>Expirada</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <a href="{{ route('licencas.index') }}" class="button button-secondary">Cancelar</a>
                <button type="submit" class="button button-primary">Salvar</button>
            </div>
        </form>
    </div>
    <script>
        (function () {
            var inputs = document.querySelectorAll('input[name="validity_type"]');
            var group = document.getElementById('expires-at-group');
            var expiresAt = document.getElementById('expires_at');
            function updateValidity() {
                var selected = document.querySelector('input[name="validity_type"]:checked');
                var noExpiry = selected && selected.value === 'no_expiry';
                group.hidden = noExpiry;
                expiresAt.disabled = noExpiry;
                expiresAt.required = !noExpiry;
            }
            inputs.forEach(function (input) { input.addEventListener('change', updateValidity); });
            updateValidity();
        })();
    </script>
@endsection
