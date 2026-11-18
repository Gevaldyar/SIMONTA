<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\OpdController;
use App\Http\Controllers\Api\WebsiteController;
use App\Http\Controllers\Api\ScanController;
use App\Http\Controllers\Api\TechnologyController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TechnologyChangeController;

Route::middleware('auth:sanctum')->group(function () {

    // Informasi pengguna yang sedang login.
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // ===================================
    // OPD MANAGEMENT
    // ===================================

    Route::apiResource('opds', OpdController::class);

    // ===================================
    // WEBSITE MANAGEMENT
    // ===================================

    Route::get(
        '/opds/{opd}/websites',
        [WebsiteController::class, 'indexForOpd']
    );

    Route::post(
        '/opds/{opd}/websites',
        [WebsiteController::class, 'storeForOpd']
    );

    Route::get(
        '/websites',
        [WebsiteController::class, 'index']
    );

    Route::get(
        '/websites/{website}',
        [WebsiteController::class, 'show']
    );

    Route::match(
        ['put', 'patch'],
        '/websites/{website}',
        [WebsiteController::class, 'update']
    );

    Route::delete(
        '/websites/{website}',
        [WebsiteController::class, 'destroy']
    );

    // ===================================
    // SCAN HISTORY & MONITORING
    // ===================================

    // Seluruh riwayat pemeriksaan.
    Route::get('/scans', [ScanController::class, 'index']);

    // Detail pemeriksaan.
    Route::get('/scans/{scan}', [ScanController::class, 'show']);

    // Riwayat pemeriksaan website tertentu.
    Route::get(
        '/websites/{website}/scans',
        [ScanController::class, 'indexForWebsite']
    );

    // Pemeriksaan website secara manual.
    // Maksimal 5 permintaan dalam satu menit.
    Route::post(
        '/websites/{website}/scans',
        [ScanController::class, 'store']
    )->middleware('throttle:5,1');

    // ===================================
// TECHNOLOGY REGISTRY
// ===================================

Route::get('/technologies', [TechnologyController::class, 'index']);

Route::get(
    '/technologies/{technology}',
    [TechnologyController::class, 'show']
);

// ===================================
// TECHNOLOGY CHANGE DETECTION
// ===================================

// Ringkasan perubahan teknologi.
// Harus diletakkan sebelum route {technologyChange}.
Route::get(
    '/technology-changes/summary',
    [TechnologyChangeController::class, 'summary']
);

// Daftar perubahan teknologi.
Route::get(
    '/technology-changes',
    [TechnologyChangeController::class, 'index']
);

// Detail satu perubahan teknologi.
Route::get(
    '/technology-changes/{technologyChange}',
    [TechnologyChangeController::class, 'show']
);

// ===================================
// DASHBOARD STATISTICS
// ===================================

Route::get(
    '/dashboard/summary',
    [DashboardController::class, 'summary']
);

Route::get(
    '/dashboard/technology-distribution',
    [DashboardController::class, 'technologyDistribution']
);

Route::get(
    '/dashboard/recent-scans',
    [DashboardController::class, 'recentScans']
);

// ===================================
// ADMINISTRATOR PROFILE & SETTINGS
// ===================================

Route::get(
    '/settings/profile',
    [ProfileController::class, 'show']
);

Route::patch(
    '/settings/profile',
    [ProfileController::class, 'update']
);

Route::put(
    '/settings/password',
    [ProfileController::class, 'updatePassword']
);

Route::get('/reports/scans/export', [ReportController::class, 'export']);

});
