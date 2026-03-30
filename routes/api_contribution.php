<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Auth\AuthenticationException;

Route::group([
    'middleware' => 'api',
    'prefix' => 'contribution'
], function () {
    // Rutas abiertas
    // Rutas autenticadas con token
    Route::group([
        'middleware' => ['auth:sanctum']
    ], function () {
        Route::get('/list_years/{type}', [App\Http\Controllers\Contribution\ImportationController::class, 'list_years']);
        Route::post('/active_affiliate_contribution', [App\Http\Controllers\Contribution\ContributionController::class, 'show']);
        Route::post('/passive_affiliate_contribution', [App\Http\Controllers\Contribution\ContributionPassiveController::class, 'show']);
        Route::get('/search_passive_affiliate_contribution', [App\Http\Controllers\Contribution\ContributionPassiveController::class, 'SearchContributionPassive']);
        Route::get('/search_active_affiliate_contribution', [App\Http\Controllers\Contribution\ContributionController::class, 'SearchContributionActive']);
        Route::apiResource('/contribution_state', App\Http\Controllers\Contribution\ContributionStateController::class)->only(['index']);
        Route::group([
            'middleware' => 'permission:delete-massive-contribution-passive'
        ], function () {
            Route::delete('/contributions_passive/massive', [App\Http\Controllers\Contribution\ContributionPassiveController::class, 'destroyMassive']);
        });
        Route::group([
            'middleware' => 'permission:delete-contribution-passive'
        ], function () {
            Route::delete('/contributions_passive/{contributionPassive}', [App\Http\Controllers\Contribution\ContributionPassiveController::class, 'destroy']);
        });
        Route::group([
            'middleware' => 'permission:delete-massive-contribution'
        ], function () {
            Route::delete('/contribution/massive', [App\Http\Controllers\Contribution\ContributionController::class, 'destroyMassive']);
        });
        Route::group([
            'middleware' => 'permission:delete-contribution'
        ], function () {
            Route::delete('/contribution/{contribution}', [App\Http\Controllers\Contribution\ContributionController::class, 'destroy']);
        });
        Route::group([
            'middleware' => 'permission:download-certifications'
        ], function () {
            Route::get('/print_contributions_passive/{affiliate_id}', [App\Http\Controllers\Contribution\ContributionPassiveController::class, 'printCertificationContributionPassive']);
            Route::get('/print_contributions_active/{affiliate_id}', [App\Http\Controllers\Contribution\ContributionController::class, 'printCertificationContributionActive']);
            Route::post('/get_report_certificate', [App\Http\Controllers\Contribution\ContributionController::class, 'get_report_certificate']);
        });

        Route::group([
            'middleware' => 'permission:read-import-payroll|create-import-payroll-senasir|create-import-payroll-command|create-import-payroll-transcript'
        ], function () {
            Route::post('/list_months_validate_senasir', [App\Http\Controllers\Contribution\ImportPayrollSenasirController::class, 'list_months_validate_senasir']);
            Route::post('/upload_copy_payroll_senasir', [App\Http\Controllers\Contribution\ImportPayrollSenasirController::class, 'upload_copy_payroll_senasir']);
            Route::post('/validation_payroll_senasir', [App\Http\Controllers\Contribution\ImportPayrollSenasirController::class, 'validation_payroll_senasir']);
            Route::post('/rollback_payroll_copy_senasir', [App\Http\Controllers\Contribution\ImportPayrollSenasirController::class, 'rollback_payroll_copy_senasir']);
            Route::post('/import_payroll_senasir_progress_bar', [App\Http\Controllers\Contribution\ImportPayrollSenasirController::class, 'import_payroll_senasir_progress_bar']);
            Route::post('/download_fail_not_found_payroll_senasir', [App\Http\Controllers\Contribution\ImportPayrollSenasirController::class, 'download_fail_not_found_payroll_senasir']);

            Route::post('/list_months_validate_command', [App\Http\Controllers\Contribution\ImportPayrollCommandController::class, 'list_months_validate_command']);
            Route::post('/upload_copy_payroll_command', [App\Http\Controllers\Contribution\ImportPayrollCommandController::class, 'upload_copy_payroll_command']);
            Route::post('/validation_payroll_command', [App\Http\Controllers\Contribution\ImportPayrollCommandController::class, 'validation_payroll_command']);
            Route::post('/rollback_payroll_copy_command', [App\Http\Controllers\Contribution\ImportPayrollCommandController::class, 'rollback_payroll_copy_command']);
            Route::post('/import_payroll_command_progress_bar', [App\Http\Controllers\Contribution\ImportPayrollCommandController::class, 'import_payroll_command_progress_bar']);
            Route::post('/download_new_affiliates_payroll_command', [App\Http\Controllers\Contribution\ImportPayrollCommandController::class, 'download_new_affiliates_payroll_command']);

            Route::post('/upload_copy_payroll_transcript', [App\Http\Controllers\Contribution\ImportPayrollTranscriptController::class, 'upload_copy_payroll_transcript']);
            Route::post('/validation_affiliate_transcript', [App\Http\Controllers\Contribution\ImportPayrollTranscriptController::class, 'validation_affiliate_transcript']);
            Route::post('/download_error_data_archive', [App\Http\Controllers\Contribution\ImportPayrollTranscriptController::class, 'download_error_data_archive']);
            Route::post('/download_data_revision', [App\Http\Controllers\Contribution\ImportPayrollTranscriptController::class, 'download_data_revision']);
            Route::post('/list_months_import_contribution_transcript', [App\Http\Controllers\Contribution\ImportPayrollTranscriptController::class, 'list_months_import_contribution_transcript']);
            Route::post('/rollback_payroll_copy_transcripts', [App\Http\Controllers\Contribution\ImportPayrollTranscriptController::class, 'rollback_payroll_copy_transcripts']);
            Route::post('/import_payroll_transcript_progress_bar', [App\Http\Controllers\Contribution\ImportPayrollTranscriptController::class, 'import_payroll_transcript_progress_bar']);
            Route::post('/import_payroll_transcript', [App\Http\Controllers\Contribution\ImportPayrollTranscriptController::class, 'import_payroll_transcript']);
            Route::post('/import_contribution_transcript', [App\Http\Controllers\Contribution\ImportPayrollTranscriptController::class, 'import_contribution_transcript']);

            Route::post('/upload_copy_payroll_filemaker', [App\Http\Controllers\Contribution\ImportPayrollFilemakerController::class, 'upload_copy_payroll_filemaker']);
            Route::post('/download_error_data_filemaker', [App\Http\Controllers\Contribution\ImportPayrollFilemakerController::class, 'download_error_data_filemaker']);
            Route::post('/validation_affiliate_filemaker', [App\Http\Controllers\Contribution\ImportPayrollFilemakerController::class, 'validation_affiliate_filemaker']);
            Route::post('/import_payroll_filemaker', [App\Http\Controllers\Contribution\ImportPayrollFilemakerController::class, 'import_payroll_filemaker']);
            Route::post('import_contribution_filemaker', [App\Http\Controllers\Contribution\ImportPayrollFilemakerController::class, 'import_contribution_filemaker']);
            Route::post('/copy_affiliate_id_filemaker', [App\Http\Controllers\Contribution\ImportPayrollFilemakerController::class, 'copy_affiliate_id_frcam_to_affiliate_id']);
        });
        Route::group([
            'middleware' => 'permission:download-report-payroll-senasir|download-report-payroll-command|download-report-payroll-transcript'
        ], function () {
            Route::post('/report_payroll_senasir', [App\Http\Controllers\Contribution\ImportPayrollSenasirController::class, 'report_payroll_senasir']);
            Route::post('/report_payroll_command', [App\Http\Controllers\Contribution\ImportPayrollCommandController::class, 'report_payroll_command']);
            Route::post('/report_import_contribution_transcript', [App\Http\Controllers\Contribution\ImportPayrollTranscriptController::class, 'report_import_contribution_transcript']);
        });
        Route::group([
            'middleware' => 'permission:read-import-contribution|create-import-senasir|create-import-command'
        ], function () {
            Route::post('/list_months_import_contribution_senasir', [App\Http\Controllers\Contribution\ImportContributionSenasirController::class, 'list_months_import_contribution_senasir']);
            Route::post('/import_create_or_update_contribution_period_senasir', [App\Http\Controllers\Contribution\ImportContributionSenasirController::class, 'import_create_or_update_contribution_period_senasir']);

            Route::post('/list_months_import_contribution_command', [App\Http\Controllers\Contribution\ImportContributionCommandController::class, 'list_months_import_contribution_command']);
            Route::post('/import_contribution_command', [App\Http\Controllers\Contribution\ImportContributionCommandController::class, 'import_contribution_command']);
        });
        Route::group([
            'middleware' => 'permission:download-report-senasir|download-report-command'
        ], function () {
            Route::post('/report_import_contribution_senasir', [App\Http\Controllers\Contribution\ImportContributionSenasirController::class, 'report_import_contribution_senasir']);
            Route::post('/report_import_contribution_command', [App\Http\Controllers\Contribution\ImportContributionCommandController::class, 'report_import_contribution_command']);
            Route::post('/report_import_reimbursement_command', [App\Http\Controllers\Contribution\ImportContributionCommandController::class, 'report_import_reimbursement_command']);
        });
        Route::post('/update_base_wages', [App\Http\Controllers\Contribution\ImportPayrollCommandController::class, 'update_base_wages']);
        Route::post('/import_contribution_eco_com', [App\Http\Controllers\Contribution\ImportContributionEcoComController::class, 'import_contribution_eco_com']);
        Route::post('/change_state_contribution_paid_eco_com', [App\Http\Controllers\Contribution\ImportContributionEcoComController::class, 'change_state_contribution_paid_eco_com']);
        Route::post('/change_state_contribution_process_eco_com', [App\Http\Controllers\Contribution\ImportContributionEcoComController::class, 'change_state_contribution_process_eco_com']);
    });
});