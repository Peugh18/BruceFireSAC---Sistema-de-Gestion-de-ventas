<?php

use App\Http\Controllers\Gerente\CompanyBankAccountController;
use App\Http\Controllers\Gerente\CompanySettingController;
use Illuminate\Support\Facades\Route;

// Rutas del rol Gerente. Se incluye desde routes/web.php dentro del grupo
// {current_team} (auth + verified + EnsureTeamMembership), igual que
// routes/vendedor.php, y además exige el rol "Gerente" (spatie/laravel-permission).
Route::prefix('gerente')
    ->name('gerente.')
    ->middleware('role:Gerente')
    ->group(function () {
        Route::get('configuracion/empresa', [CompanySettingController::class, 'edit'])->name('configuracion.empresa.edit');
        Route::post('configuracion/empresa', [CompanySettingController::class, 'update'])->name('configuracion.empresa.update');

        Route::post('configuracion/empresa/cuentas-bancarias', [CompanyBankAccountController::class, 'store'])->name('configuracion.cuentas-bancarias.store');
        Route::put('configuracion/empresa/cuentas-bancarias/{cuenta_bancaria}', [CompanyBankAccountController::class, 'update'])->name('configuracion.cuentas-bancarias.update');
        Route::delete('configuracion/empresa/cuentas-bancarias/{cuenta_bancaria}', [CompanyBankAccountController::class, 'destroy'])->name('configuracion.cuentas-bancarias.destroy');
    });
