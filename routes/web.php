<?php

use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\PublicUploadController;
use App\Http\Controllers\ShareableLinksController;
use Common\Core\Controllers\HomeController;
use Common\Pages\CustomPageController;
use Illuminate\Support\Facades\Route;

//FRONT-END ROUTES THAT NEED TO BE PRE-RENDERED
Route::get('/', [PublicUploadController::class, 'index']);
Route::get('drive/s/{hash}', [ShareableLinksController::class, 'show']);

// TRANSFER ROUTES
Route::get('transfer', [HomeController::class, 'render'])->name('transfer.upload');
Route::get('transfer/{hash}', [HomeController::class, 'render'])->name('transfer.show');

// TUS TRANSFER ROUTES
Route::get('t/{uuid}', [HomeController::class, 'render'])->name('tus.transfer.show');
Route::get('t/{uuid}/download/{fileId?}', [HomeController::class, 'render'])->name('transfer.download');

// DOWNLOAD PAGE ROUTES
Route::get('d/{slug}', [HomeController::class, 'render'])->name('download.page');

Route::get('contact', [HomeController::class, 'render']);
Route::get('pages/{slugOrId}', [CustomPageController::class, 'show']);
Route::get('login', [HomeController::class, 'render'])->name('login');
Route::get('register', [HomeController::class, 'render'])->name('register');
Route::get('forgot-password', [HomeController::class, 'render']);
Route::get('pricing', '\Common\Billing\PricingPageController');

//CATCH ALL ROUTES AND REDIRECT TO HOME
Route::fallback([HomeController::class, 'render']);
