<?php

use App\Http\Controllers\Api\CatalogueController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\DownloadController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->group(function () {
    Route::get('manifest', [CatalogueController::class, 'manifest'])->name('manifest');
    Route::get('expansions', [CatalogueController::class, 'expansions'])->name('expansions');
    Route::get('zones', [CatalogueController::class, 'zones'])->name('zones');
    Route::get('zones/{zone}', [CatalogueController::class, 'zone'])->name('zone');
    Route::get('files/{file}/download', [DownloadController::class, 'file'])->name('files.download');
    Route::get('zip', [DownloadController::class, 'zip'])->middleware('throttle:zips')->name('zip');

    Route::post('zones/{zone}/versions', [UploadController::class, 'store'])
        ->middleware(['auth:sanctum', 'abilities:upload', 'throttle:uploads'])
        ->name('versions.store');
});
