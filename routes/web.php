<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConfiguracoesController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DominioController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\LicencaController;
use App\Http\Controllers\ListaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SugestaoDominioController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RpzController;
use App\Http\Controllers\SegurancaController;
use App\Http\Controllers\AnatelController;
use App\Http\Controllers\AnatelImportController;
use App\Http\Controllers\ServidorController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt')->middleware('throttle:10,1');

Route::get('/cadastro', [RegistrationController::class, 'show'])->name('register');
Route::post('/cadastro', [RegistrationController::class, 'store'])->name('register.store')->middleware('throttle:10,1');

Route::get('/rpz/{token}.zone', [RpzController::class, 'show'])->middleware('throttle:60,1')
    ->where('token', '[A-Za-z0-9]+')
    ->name('rpz.show');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('servidores', ServidorController::class)->parameters(['servidores' => 'servidor']);
    Route::post('/servidores/{servidor}/listas/{lista}/attach', [ServidorController::class, 'attachLista'])->name('servidores.listas.attach');
    Route::delete('/servidores/{servidor}/listas/{lista}/detach', [ServidorController::class, 'detachLista'])->name('servidores.listas.detach');

    Route::resource('listas', ListaController::class)->only(['index']);

    Route::get('/perfil', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/perfil/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::get('/perfil/senha', [ProfileController::class, 'editPassword'])->name('profile.password');
    Route::put('/perfil/senha', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    Route::resource('sugestoes', SugestaoDominioController::class)->only(['index', 'create', 'store']);

    Route::get('/consulta', [ConsultaController::class, 'index'])->name('consulta.index');

    Route::middleware('admin')->group(function () {
        Route::resource('empresas', EmpresaController::class)->except(['show']);
        Route::resource('licencas', LicencaController::class);
        Route::resource('listas', ListaController::class)->except(['index', 'show']);
        Route::patch('/listas/{lista}/toggle-sync', [ListaController::class, 'toggleSync'])->name('listas.toggle-sync');
        Route::post('/listas/{lista}/sync-now', [ListaController::class, 'syncNow'])->name('listas.sync-now');
        Route::post('/listas/{lista}/anatel/imports', [AnatelImportController::class, 'store'])->name('anatel.imports.store');
        Route::get('/listas/{lista}/anatel/history', [AnatelController::class, 'history'])->name('anatel.history');
        Route::get('/listas/{lista}/anatel/imports/{import}/new.txt', [AnatelImportController::class, 'downloadNew'])->name('anatel.imports.new');
        Route::get('/listas/{lista}/anatel/exclusions', [AnatelController::class, 'exclusions'])->name('anatel.exclusions');
        Route::post('/listas/{lista}/anatel/exclusions', [AnatelController::class, 'storeExclusion'])->name('anatel.exclusions.store');
        Route::patch('/listas/{lista}/anatel/exclusions/{exclusion}', [AnatelController::class, 'toggleExclusion'])->name('anatel.exclusions.toggle');
        Route::delete('/listas/{lista}/anatel/exclusions/{exclusion}', [AnatelController::class, 'destroyExclusion'])->name('anatel.exclusions.destroy');
        Route::post('/listas/{lista}/anatel/exclusions/legacy', [AnatelController::class, 'importLegacy'])->name('anatel.exclusions.legacy');

        Route::get('/listas/{lista}/dominios', [DominioController::class, 'index'])->name('listas.dominios.index');
        Route::post('/listas/{lista}/dominios', [DominioController::class, 'store'])->name('listas.dominios.store');
        Route::post('/listas/{lista}/dominios/bulk', [DominioController::class, 'bulkStore'])->name('listas.dominios.bulk');
        Route::patch('/dominios/{dominio}/toggle', [DominioController::class, 'toggle'])->name('dominios.toggle');
        Route::delete('/dominios/{dominio}', [DominioController::class, 'destroy'])->name('dominios.destroy');

        Route::post('/sugestoes/{sugestao}/aprovar', [SugestaoDominioController::class, 'aprovar'])->name('sugestoes.aprovar');
        Route::post('/sugestoes/{sugestao}/rejeitar', [SugestaoDominioController::class, 'rejeitar'])->name('sugestoes.rejeitar');

        Route::post('/servidores/{servidor}/ip-restriction/toggle', [ServidorController::class, 'toggleIpRestriction'])->name('servidores.ip-restriction.toggle');
        Route::post('/servidores/{servidor}/ips', [ServidorController::class, 'addAllowedIp'])->name('servidores.ips.store');
        Route::delete('/servidores/{servidor}/ips/{ip}', [ServidorController::class, 'removeAllowedIp'])->name('servidores.ips.destroy');

        Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
        Route::get('/seguranca', [SegurancaController::class, 'index'])->name('seguranca.index');

        Route::resource('usuarios', UserController::class)->except(['show']);
        Route::post('/usuarios/{usuario}/resetar-senha', [UserController::class, 'resetPassword'])->name('usuarios.reset-password');

        Route::get('/configuracoes', [ConfiguracoesController::class, 'index'])->name('configuracoes.index');
        Route::put('/configuracoes/telegram', [ConfiguracoesController::class, 'updateTelegram'])->name('configuracoes.telegram.update');
        Route::post('/configuracoes/telegram/testar', [ConfiguracoesController::class, 'testTelegram'])->name('configuracoes.telegram.test');
    });

    Route::get('/empresas/{empresa}', [EmpresaController::class, 'show'])->name('empresas.show');
    Route::get('/listas/{lista}', [ListaController::class, 'show'])->name('listas.show');
    Route::get('/listas/{lista}/historico', [ListaController::class, 'history'])->name('listas.historico');
});
