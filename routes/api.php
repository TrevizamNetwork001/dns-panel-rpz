<?php

use App\Http\Controllers\Api\DominioController;
use App\Http\Controllers\Api\EmpresaController;
use App\Http\Controllers\Api\LicencaController;
use App\Http\Controllers\Api\ListaController;
use App\Http\Controllers\Api\ServidorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['api.auth'])->group(function () {
    Route::get('/user', function (Request $request) {
        $user = $request->attributes->get('api_user');

        if ($user) {
            return response()->json(['data' => [
                'type' => 'user',
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'empresa_id' => $user->empresa_id,
            ]]);
        }

        $empresa = $request->attributes->get('api_empresa');

        return response()->json(['data' => [
            'type' => 'empresa_key',
            'empresa_id' => $empresa->id,
            'empresa_nome' => $empresa->nome,
        ]]);
    });

    Route::apiResource('empresas', EmpresaController::class)->except(['destroy']);
    Route::apiResource('licencas', LicencaController::class);
    Route::apiResource('servidores', ServidorController::class)->parameters(['servidores' => 'servidor']);
    Route::apiResource('listas', ListaController::class)->except(['destroy']);

    Route::get('/listas/{lista}/dominios', [DominioController::class, 'index'])->name('api.listas.dominios.index');
    Route::post('/listas/{lista}/dominios', [DominioController::class, 'store'])->name('api.listas.dominios.store');
    Route::post('/listas/{lista}/dominios/bulk', [DominioController::class, 'bulkStore'])->name('api.listas.dominios.bulk');
    Route::patch('/dominios/{dominio}/toggle', [DominioController::class, 'toggle'])->name('api.dominios.toggle');
    Route::delete('/dominios/{dominio}', [DominioController::class, 'destroy'])->name('api.dominios.destroy');
});
