<?php

use App\Http\Controllers\RpzExceptionRequestController;
use App\Http\Controllers\RpzOperationsController;
use App\Http\Controllers\RpzListLinkRequestController;
use App\Http\Controllers\TenantRpzController;
use App\Http\Middleware\AuthenticateRpzOperations;
use App\Http\Middleware\AuthenticateTenantApi;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['throttle:120,1', AuthenticateTenantApi::class])->group(function (): void {
    Route::get('listas', [TenantRpzController::class, 'listas']);
    Route::get('bloqueios', [TenantRpzController::class, 'index']);
    Route::get('bloqueios/{id}', [TenantRpzController::class, 'show'])->whereNumber('id')->name('tenant-api.bloqueios.show');
    Route::post('listas/{lista}/bloqueios', [TenantRpzController::class, 'store'])->whereNumber('lista');
    Route::patch('bloqueios/{id}', [TenantRpzController::class, 'update'])->whereNumber('id');
    Route::delete('bloqueios/{id}', [TenantRpzController::class, 'destroy'])->whereNumber('id');
    Route::get('publicacao', [TenantRpzController::class, 'publicacao']);
    Route::put('servidores/confirmacao', [TenantRpzController::class, 'confirmar'])->name('tenant-api.server-report');
    Route::get('servidores', [TenantRpzController::class, 'servidores']);
});

Route::prefix('v1/operacao')->middleware(['throttle:60,1', AuthenticateRpzOperations::class])->group(function (): void {
    Route::get('empresas', [RpzOperationsController::class, 'empresas']);
    Route::get('empresas/{empresa}/excecoes', [RpzExceptionRequestController::class, 'index'])->whereNumber('empresa');
    Route::get('empresas/{empresa}/diagnostico-dominio', [RpzExceptionRequestController::class, 'diagnose'])->whereNumber('empresa');
    Route::get('empresas/{empresa}/listas-disponiveis', [RpzListLinkRequestController::class, 'listas'])->whereNumber('empresa');
    Route::get('empresas/{empresa}/listas/{lista}/conteudo', [RpzOperationsController::class, 'conteudoLista'])
        ->whereNumber(['empresa', 'lista']);
    Route::get('empresas/{empresa}/vinculos-listas', [RpzOperationsController::class, 'vinculos'])->whereNumber('empresa');
    Route::get('empresas/{empresa}/sincronizacao', [RpzOperationsController::class, 'sincronizacao'])->whereNumber('empresa');
});

Route::prefix('v1/operacao')->middleware('throttle:20,1')->group(function (): void {
    Route::post('empresas/{empresa}/listas/{lista}/solicitacoes-dominio', [RpzExceptionRequestController::class, 'prepareManualDomain'])
        ->whereNumber(['empresa', 'lista'])->middleware(AuthenticateRpzOperations::class);
    Route::post('empresas/{empresa}/solicitacoes-vinculo', [RpzListLinkRequestController::class, 'prepare'])
        ->whereNumber('empresa')->middleware(AuthenticateRpzOperations::class);
    Route::post('empresas/{empresa}/solicitacoes-excecao', [RpzExceptionRequestController::class, 'prepare'])
        ->whereNumber('empresa')->middleware(AuthenticateRpzOperations::class);
    Route::post('solicitacoes-excecao/{id}/confirmacao', [RpzExceptionRequestController::class, 'confirm'])
        ->whereUuid('id');
});
