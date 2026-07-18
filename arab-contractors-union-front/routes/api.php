<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ContractorController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PenaltyController;
use App\Http\Controllers\Api\TenderController;
use App\Http\Controllers\Api\InspectionController;
use App\Http\Controllers\Api\DocumentController;

// ===========================
// Auth (public)
// ===========================
Route::prefix('v1/auth')->group(function () {
    Route::post('login',  [AuthController::class, 'login']);
});

// ===========================
// Protected routes (Sanctum)
// ===========================
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('v1/auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',      [AuthController::class, 'me']);
    });

    // Dashboard
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);

    // Contractors
    Route::apiResource('contractors', ContractorController::class)
         ->only(['index', 'store', 'show', 'destroy']);
    Route::post('contractors/{contractor}/qr', [ContractorController::class, 'generateQR']);

    // Memberships
    Route::get('memberships/pending',             [MembershipController::class, 'pending']);
    Route::get('memberships',                     [MembershipController::class, 'index']);
    Route::post('memberships',                    [MembershipController::class, 'store']);
    Route::post('memberships/{membership}/approve', [MembershipController::class, 'approve']);
    Route::post('memberships/{membership}/reject',  [MembershipController::class, 'reject']);

    // Payments
    Route::get('payments/transactions',  [PaymentController::class, 'index']);
    Route::post('payments/transactions', [PaymentController::class, 'store']);

    // Penalties
    Route::get('penalties',                    [PenaltyController::class, 'index']);
    Route::post('penalties',                   [PenaltyController::class, 'store']);
    Route::patch('penalties/{penalty}/pay',    [PenaltyController::class, 'markPaid']);

    // Tenders
    Route::apiResource('tenders', TenderController::class);

    // Inspections
    Route::get('inspections',                [InspectionController::class, 'index']);
    Route::post('inspections',               [InspectionController::class, 'store']);
    Route::patch('inspections/{inspection}', [InspectionController::class, 'update']);

    // Documents
    Route::get('documents',               [DocumentController::class, 'index']);
    Route::post('documents',              [DocumentController::class, 'store']);
    Route::delete('documents/{document}', [DocumentController::class, 'destroy']);
});
