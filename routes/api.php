<?php

use App\Http\Controllers\DriveEntriesController;
use App\Http\Controllers\DuplicateEntriesController;
use App\Http\Controllers\EntrySyncInfoController;
use App\Http\Controllers\DownloadPageController;
use App\Http\Controllers\FcmTokenController;
use App\Http\Controllers\FolderPathController;
use App\Http\Controllers\FoldersController;
use App\Http\Controllers\MoveFileEntriesController;
use App\Http\Controllers\ShareableLinkPasswordController;
use App\Http\Controllers\ShareableLinksController;
use App\Http\Controllers\SharesController;
use App\Http\Controllers\SpaceUsageController;
use App\Http\Controllers\StarredEntriesController;
use App\Http\Controllers\UserFoldersController;
use Illuminate\Support\Facades\Route;

// prettier-ignore
Route::group(['prefix' => 'v1'], function() {
  // PUBLIC FILE TRANSFER ROUTES (NO AUTH REQUIRED)
  Route::post('transfer', [\App\Http\Controllers\TransferController::class, 'store']);
  Route::get('transfer/{hash}', [\App\Http\Controllers\TransferController::class, 'show']);
  Route::post('transfer/{hash}/verify-password', [\App\Http\Controllers\TransferController::class, 'verifyPassword'])
    ->middleware('App\Http\Middleware\RateLimitPasswordAttempts');
  
  // NEW METADATA API ENDPOINT
  Route::get('transfers/{uuid}', [\App\Http\Controllers\TransferController::class, 'getMetadata']);
  
  // PROTECTED DOWNLOAD ROUTES (CHECK PASSWORD)
  Route::middleware(['App\Http\Middleware\CheckTransferPassword'])->group(function () {
    Route::get('transfer/{hash}/download', [\App\Http\Controllers\TransferController::class, 'downloadAll']);
    Route::get('transfer/{hash}/file/{fileId}/download', [\App\Http\Controllers\TransferController::class, 'downloadFile']);
    Route::get('transfer/{hash}/file/{fileId}/preview', [\App\Http\Controllers\TransferController::class, 'preview']);
  });
  
  Route::delete('transfer/{hash}', [\App\Http\Controllers\TransferController::class, 'destroy']);
  
  // TUS UPLOAD ROUTES
  Route::any('transfer/tus/{any?}', [\App\Http\Controllers\TransferController::class, 'tusUpload'])->where('any', '.*');
  
  // TUS TRANSFER UUID ROUTES
  Route::get('tus/transfer/{uuid}', [\App\Http\Controllers\TransferController::class, 'showTus']);
  
  // PROTECTED TUS DOWNLOAD ROUTES (CHECK PASSWORD)
  Route::middleware(['App\Http\Middleware\CheckTransferPassword'])->group(function () {
    Route::get('tus/transfer/{uuid}/download/{fileId?}', [\App\Http\Controllers\TransferController::class, 'downloadTusFile']);
  });
  
  // THUMBNAIL API ROUTES (NO AUTH REQUIRED)
  Route::get('thumbnails/{hash}', [\App\Http\Controllers\ThumbnailController::class, 'show']);
  Route::post('thumbnails/batch', [\App\Http\Controllers\ThumbnailController::class, 'batch']);
  
  // ADMIN TRANSFER ROUTES (AUTH REQUIRED)
  Route::middleware(['auth', 'isAdmin'])->group(function () {
    Route::get('admin/transfers', [\App\Http\Controllers\AdminTransferController::class, 'index']);
    Route::get('admin/transfers/{transfer}', [\App\Http\Controllers\AdminTransferController::class, 'show']);
    Route::delete('admin/transfers/{transferIds}', [\App\Http\Controllers\AdminTransferController::class, 'destroy']);
    Route::get('admin/transfers/analytics', [\App\Http\Controllers\AdminTransferController::class, 'analytics']);
    Route::post('admin/transfers/cleanup', [\App\Http\Controllers\AdminTransferController::class, 'cleanup']);
  });
  
  Route::group(['middleware' => ['optionalAuth:sanctum', 'verified', 'verifyApiAccess']], function () {
    // SHARING
    Route::post('file-entries/{fileEntry}/share', [
      SharesController::class,
      'addUsers',
    ]);
    Route::post('file-entries/{id}/unshare', [
      SharesController::class,
      'removeUser',
    ]);
    Route::put('file-entries/{fileEntry}/change-permissions', [
      SharesController::class,
      'changePermissions',
    ]);

    // SHAREABLE LINK
    Route::get('file-entries/{id}/shareable-link', [
      ShareableLinksController::class,
      'show',
    ]);
    Route::post('file-entries/{id}/shareable-link', [
      ShareableLinksController::class,
      'store',
    ]);
    Route::put('file-entries/{id}/shareable-link', [
      ShareableLinksController::class,
      'update',
    ]);
    Route::delete('file-entries/{id}/shareable-link', [
      ShareableLinksController::class,
      'destroy',
    ]);
    Route::post('shareable-links/{linkId}/import', [
      SharesController::class,
      'addCurrentUser',
    ]);

    // ENTRIES
    Route::get('drive/file-entries/{fileEntry}/model', [
      DriveEntriesController::class,
      'showModel',
    ]);
    Route::get('drive/file-entries', [
      DriveEntriesController::class,
      'index',
    ]);
    Route::post('file-entries/sync-info', [
      EntrySyncInfoController::class,
      'index',
    ]);
    Route::post('file-entries/move', [
      MoveFileEntriesController::class,
      'move',
    ]);
    Route::post('file-entries/duplicate', [
      DuplicateEntriesController::class,
      'duplicate',
    ]);

    // FOLDERS
    Route::post('folders', [FoldersController::class, 'store']);
    Route::get('users/{userId}/folders', [
      UserFoldersController::class,
      'index',
    ]);
    Route::get('folders/{hash}/path', [
      FolderPathController::class,
      'show',
    ]);

    // Labels
    Route::post('file-entries/star', [
      StarredEntriesController::class,
      'add',
    ]);
    Route::post('file-entries/unstar', [
      StarredEntriesController::class,
      'remove',
    ]);

    //SPACE USAGE
    Route::get('user/space-usage', [SpaceUsageController::class, 'index']);

    // FCM TOKENS
    Route::post('fcm-token', [FcmTokenController::class, 'store']);
  });

  //SHAREABLE LINKS PREVIEW (NO AUTH NEEDED)
  Route::get('shareable-links/{hash}', [
    ShareableLinksController::class,
    'show',
  ]);
  Route::post('shareable-links/{linkHash}/check-password', [
    ShareableLinkPasswordController::class,
    'check',
  ]);
  
  // DOWNLOAD PAGE ROUTES (NO AUTH NEEDED)
  Route::get('download/{slug}', [DownloadPageController::class, 'show']);
  Route::post('download', [DownloadPageController::class, 'store']);
});
