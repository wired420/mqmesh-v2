<?php

use App\Http\Controllers\DepotWebhookController;
use App\Http\Controllers\DownloadController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Volt::route('/', 'home')->name('home');
Volt::route('zones', 'zones.index')->name('zones.index');
Volt::route('zones/{zone}', 'zones.show')->name('zones.show');

Route::get('files/{file}/download', [DownloadController::class, 'file'])->name('files.download');
Route::get('download/zip', [DownloadController::class, 'zip'])->middleware('throttle:zips')->name('downloads.zip');

// The old site's file paths, still used by the existing updaters' feeds.
Route::get('resources/meshes/{name}.navmesh', [DownloadController::class, 'legacy'])->defaults('type', 'mesh')->where('name', '[A-Za-z0-9_-]+');
Route::get('resources/doors/{name}_doors.json', [DownloadController::class, 'legacy'])->defaults('type', 'doors')->where('name', '[A-Za-z0-9_-]+');

Route::post('hooks/gitlab', DepotWebhookController::class)
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
    ->middleware('throttle:30,1');

Route::view('about', 'about')->name('about');
Route::view('updater', 'updater')->name('updater');
Route::get('updater/download/{file}', [DownloadController::class, 'updater'])
    ->where('file', '[A-Za-z0-9._-]+')
    ->name('updater.download');
Route::view('api-docs', 'api-docs')->name('api.docs');

Route::middleware(['auth'])->group(function () {
    // Uploading (and API tokens, which can upload) needs a confirmed email.
    Volt::route('upload', 'uploads.create')->middleware('verified')->name('uploads.create');
    Volt::route('my/uploads', 'uploads.mine')->name('dashboard');

    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    Volt::route('settings/tokens', 'settings.tokens')->middleware('verified')->name('settings.tokens');

    Volt::route('moderation', 'moderation')->middleware('can:moderate')->name('moderation');
    Volt::route('admin/users', 'admin.users')->middleware('can:admin')->name('admin.users');
});

require __DIR__.'/auth.php';
