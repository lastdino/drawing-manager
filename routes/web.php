<?php

use Illuminate\Support\Facades\Route;
use Lastdino\DrawingManager\Http\Controllers\DownloadController;
use Lastdino\DrawingManager\Livewire\Drawings\Index as PackageDrawingsIndex;

Route::group([
    'prefix' => config('drawing-manager.route_prefix', 'drawings'),
    'middleware' => config('drawing-manager.middleware', ['web', 'auth']),
], function () {
    // 一覧ページ（Livewire クラスベース）
    Route::get('/', PackageDrawingsIndex::class)->name('drawings.index');

    // ダウンロード系
    Route::get('/{drawing}/latest/{type}', [DownloadController::class, 'latest'])
        ->name('drawings.download.latest');

    Route::get('/revisions/{media}', [DownloadController::class, 'revision'])
        ->name('drawings.download.revision');
});
