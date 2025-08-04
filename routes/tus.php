<?php

use TusPhp\Tus\Server as TusServer;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Events\TransferCreated;
use App\Models\Transfer;
use App\Models\TransferFile;

/*
|--------------------------------------------------------------------------
| TUS Upload Routes
|--------------------------------------------------------------------------
|
| These routes handle resumable file uploads using the TUS protocol.
| The server is configured with event hooks for transfer management.
|
*/

// TUS server configuration
$server = new TusServer('redis'); // Using Redis cache for upload metadata

// Configure storage - will use configured disk from filesystems.php
$uploadDisk = config('tus.disk', 'uploads');
$server->setApiPath('/tus') // Tus server api path
       ->setUploadDir(storage_path('app/tus-uploads')); // Temp upload directory

// Set maximum upload size (5GB+)
$server->setMaxUploadSize(config('tus.max_upload_size', 5 * 1024 * 1024 * 1024)); // 5GB default

// Event hook: On upload start
$server->event()->addListener('tus-server.upload.created', function (TusPhp\Events\TusEvent $event) {
    $file = $event->getFile();
    $request = $event->getRequest();
    
    // Extract metadata from upload
    $metadata = $file->getMetadata();
    $fileName = $metadata['filename'] ?? 'unknown';
    $fileSize = $file->getFileSize();
    
    // Create transfer record
    $transfer = Transfer::create([
        'uuid' => \Illuminate\Support\Str::uuid(),
        'total_size' => $fileSize,
        'status' => 'uploading',
        'expiry_at' => now()->addDays(7), // 7 days default expiry
        'password_hash' => null, // Will be set if password protection is enabled
        'download_count' => 0,
    ]);
    
    // Create transfer file record
    TransferFile::create([
        'transfer_id' => $transfer->id,
        'original_name' => $fileName,
        'size' => $fileSize,
        'mime_type' => $metadata['filetype'] ?? 'application/octet-stream',
        'upload_key' => $file->getKey(), // TUS upload key for tracking
    ]);
    
    // Store transfer ID in upload metadata for later reference
    $file->setMetadata(array_merge($metadata, ['transfer_id' => $transfer->id]));
});

// Event hook: On upload completion
$server->event()->addListener('tus-server.upload.complete', function (TusPhp\Events\TusEvent $event) {
    $file = $event->getFile();
    $metadata = $file->getMetadata();
    $transferId = $metadata['transfer_id'] ?? null;
    
    if (!$transferId) {
        return;
    }
    
    $transfer = Transfer::find($transferId);
    if (!$transfer) {
        return;
    }
    
    // Move uploaded file to permanent storage
    $uploadedFilePath = $file->getFilePath();
    $destinationPath = 'transfers/' . $transfer->uuid . '/' . ($metadata['filename'] ?? 'file');
    
        // Move to configured storage disk
        $uploadDisk = config('tus.disk', 'uploads');
        $disk = \Illuminate\Support\Facades\Storage::disk($uploadDisk);
        $disk->put($destinationPath, file_get_contents($uploadedFilePath));
    
    // Update transfer and file records
    $transfer->update([
        'status' => 'completed',
        'completed_at' => now(),
    ]);
    
    $transferFile = $transfer->transferFiles()->where('upload_key', $file->getKey())->first();
    if ($transferFile) {
        $transferFile->update([
            'storage_path' => $destinationPath,
        ]);
    }
    
    // Clean up temporary file
    if (file_exists($uploadedFilePath)) {
        unlink($uploadedFilePath);
    }
    
    // Fire transfer created event
    event(new TransferCreated($transfer));
});

// Event hook: On upload error
$server->event()->addListener('tus-server.upload.error', function (TusPhp\Events\TusEvent $event) {
    $file = $event->getFile();
    $metadata = $file->getMetadata();
    $transferId = $metadata['transfer_id'] ?? null;
    
    if ($transferId) {
        $transfer = Transfer::find($transferId);
        if ($transfer) {
            $transfer->update(['status' => 'failed']);
        }
    }
});

// Handle all TUS requests
Route::any('/tus/{path?}', function (Request $request, $path = null) use ($server) {
    // Add path info for TUS server
    if ($path) {
        $request->server->set('PATH_INFO', '/tus/' . $path);
    }
    
    $response = $server->serve();
    
    return response($response->getBody(), $response->getStatusCode())
        ->withHeaders($response->getHeaders());
})->where('path', '.*')->name('tus.upload');
