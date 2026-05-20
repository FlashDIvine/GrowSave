<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RoomRequestController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\CashBalanceController;
use App\Http\Controllers\Api\TransactionController;


/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/profile', [ProfileController::class, 'update']);

});
Route::middleware(['auth:sanctum', 'admin'])->group(function () {

    Route::get('/admin-test', function () {

        return response()->json([
            'success' => true,
            'message' => 'Admin middleware berhasil'
        ]);

    });

});

Route::middleware(['auth:sanctum', 'approved'])->group(function () {

    Route::get('/approved-test', function () {

        return response()->json([
            'success' => true,
            'message' => 'Approved middleware berhasil'
        ]);

    });

});

Route::middleware(['auth:sanctum', 'admin'])->group(function () {

    Route::get('/room/requests', [RoomRequestController::class, 'index']);

    Route::post('/room/approve/{id}', [RoomRequestController::class, 'approve']);

    Route::post('/room/reject/{id}', [RoomRequestController::class, 'reject']);

    /*
    |--------------------------------------------------------------------------
    | ANNOUNCEMENT (ADMIN ONLY)
    |--------------------------------------------------------------------------
    */

    Route::post('/announcements', [AnnouncementController::class, 'store']);

    Route::put('/announcements/{id}', [AnnouncementController::class, 'update']);

    Route::delete('/announcements/{id}', [AnnouncementController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | BILL / TAGIHAN (ADMIN ONLY)
    |--------------------------------------------------------------------------
    */

    Route::post('/bills', [BillController::class, 'store']);

    Route::put('/bills/{id}', [BillController::class, 'update']);

    Route::delete('/bills/{id}', [BillController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD (ADMIN ONLY)
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard', [DashboardController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | TRANSAKSI MANUAL (ADMIN ONLY)
    |--------------------------------------------------------------------------
    */

    Route::post('/transactions', [TransactionController::class, 'store']);

});

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/room', [RoomController::class, 'show']);

});

/*
|--------------------------------------------------------------------------
| APPROVED USER (ADMIN + WARGA YANG SUDAH DISETUJUI)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'approved'])->group(function () {

    Route::get('/announcements', [AnnouncementController::class, 'index']);

    Route::get('/announcements/{id}', [AnnouncementController::class, 'show']);

    /*
    |--------------------------------------------------------------------------
    | BILL / TAGIHAN (READ)
    |--------------------------------------------------------------------------
    */

    Route::get('/bills', [BillController::class, 'index']);

    Route::get('/bills/{id}', [BillController::class, 'show']);

    /*
    |--------------------------------------------------------------------------
    | PAYMENT / PEMBAYARAN
    |--------------------------------------------------------------------------
    */

    Route::post('/payments', [PaymentController::class, 'store']);

    /*
    |--------------------------------------------------------------------------
    | TRANSPARANSI KAS
    |--------------------------------------------------------------------------
    */

    Route::get('/cash-balance', [CashBalanceController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | TRANSAKSI KAS
    |--------------------------------------------------------------------------
    */

    Route::get('/transactions', [TransactionController::class, 'index']);

});

/*
|--------------------------------------------------------------------------
| WEBHOOK MIDTRANS (PUBLIC — TANPA AUTH)
|--------------------------------------------------------------------------
*/

Route::post('/webhooks/midtrans', [PaymentController::class, 'webhook']);