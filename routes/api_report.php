<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Auth\AuthenticationException;
use App\Http\Controllers\KioskAuthenticationDataController;

Route::group([
    'middleware' => 'api',
    'prefix' => 'report'
], function () {
    // Rutas abiertas
    Route::post('register_auth_kiosk', [KioskAuthenticationDataController::class, 'register_auth_kiosk']);
    // Rutas autenticadas con token
    Route::group([
        'middleware' => ['auth:sanctum']
    ], function () {
        // reportes requeridos de BE
        Route::get('report_affiliates_spouses', [App\Http\Controllers\ReportController::class, 'report_affiliates_spouses']);
        Route::post('report_retirement_funds', [App\Http\Controllers\ReportController::class, 'report_retirement_funds']);
        Route::post('report_payments_beneficiaries', [App\Http\Controllers\ReportController::class, 'report_payments_beneficiaries']);
        Route::get('report_affiliates_similar', [App\Http\Controllers\ReportController::class, 'report_affiliates_similar']);
        Route::post('report_procedures_frcam', [App\Http\Controllers\ReportController::class, 'report_procedures_frcam']);
    });
});
