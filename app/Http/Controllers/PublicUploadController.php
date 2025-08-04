<?php

namespace App\Http\Controllers;

use App\Models\Transfer;
use Common\Core\BaseController;
use Common\Files\Actions\CreateFileEntry;
use Common\Files\Actions\StoreFile;
use Common\Files\Actions\ValidateFileUpload;
use Common\Files\Events\FileUploaded;
use Common\Files\FileEntry;
use Common\Files\FileEntryPayload;
use Common\Files\Response\FileResponseFactory;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PublicUploadController extends BaseController
{
    public function __construct(
        protected Request $request,
        protected FileEntry $entry,
    ) {
        // No authentication middleware - public access
    }

    /**
     * Serve React SPA for public upload page
     */
    public function index()
    {
        return view('app');
    }

    /**
     * Upload a file without authentication
     */
    public function store()
    {
        $this->validate($this->request, [
            'file' => [
                'required',
                'file',
                function ($attribute, UploadedFile $value, $fail) {
                    $payload = new FileEntryPayload([
                        'name' => $value->getClientOriginalName(),
                        'file_name' => $value->getClientOriginalName(),
                        'extension' => $value->getClientOriginalExtension(),
                        'size' => $value->getSize(),
                        'mime' => $value->getMimeType(),
                    ]);
                    
                    $errors = app(ValidateFileUpload::class)->execute([
                        'extension' => $payload->clientExtension,
                        'size' => $payload->size,
                    ]);
                    if ($errors) {
                        $fail($errors->first());
                    }
                },
            ],
            'description' => 'nullable|string|max:500',
        ]);

        $file = $this->request->file('file');
        $description = $this->request->get('description');
        
        // Generate unique hash for public access
        $hash = Str::random(32);
        
        // Create file entry payload
        $payload = new FileEntryPayload([
            'name' => $file->getClientOriginalName(),
            'file_name' => $file->getClientOriginalName(),
            'extension' => $file->getClientOriginalExtension(),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
            'description' => $description,
            'hash' => $hash,
            'type' => 'file',
            'public' => true,
            'workspace_id' => 0, // No workspace for public files
            'parent_id' => null,
            'owner_id' => null, // No owner for public files
        ]);

        // Store the file
        app(StoreFile::class)->execute($payload, ['file' => $file]);

        // Create file entry
        $fileEntry = app(CreateFileEntry::class)->execute($payload);

        // Update with generated hash for public access
        $fileEntry->update(['hash' => $hash]);

        event(new FileUploaded($fileEntry));

        return $this->success([
            'fileEntry' => $fileEntry,
            'hash' => $hash,
            'downloadUrl' => url("api/v1/public/download/{$hash}"),
            'shareUrl' => url("transfer/{$hash}"),
            'expiresAt' => now()->addDays(7)->toISOString(), // Files expire after 7 days
        ], 201);
    }

    /**
     * Show file information by hash
     */
    public function show(string $hash)
    {
        $fileEntry = $this->entry->where('hash', $hash)->firstOrFail();
        
        // Check if file has expired (7 days)
        if ($fileEntry->created_at->addDays(7)->isPast()) {
            abort(410, 'File has expired');
        }

        return $this->success([
            'fileEntry' => $fileEntry,
            'downloadUrl' => url("api/v1/public/download/{$hash}"),
            'expiresAt' => $fileEntry->created_at->addDays(7)->toISOString(),
        ]);
    }

    /**
     * Download file by hash
     */
    public function download(string $hash, FileResponseFactory $response)
    {
        $fileEntry = $this->entry->where('hash', $hash)->firstOrFail();
        
        // Check if file has expired (7 days)
        if ($fileEntry->created_at->addDays(7)->isPast()) {
            abort(410, 'File has expired');
        }

        try {
            return $response->create($fileEntry);
        } catch (FileNotFoundException $e) {
            abort(404, 'File not found');
        }
    }

    /**
     * Delete file by hash (optional - for cleanup)
     */
    public function destroy(string $hash)
    {
        $fileEntry = $this->entry->where('hash', $hash)->firstOrFail();
        
        // Delete the physical file
        if ($fileEntry->disk_prefix) {
            Storage::disk($fileEntry->disk)->delete($fileEntry->disk_prefix);
        }
        
        // Delete the entry
        $fileEntry->delete();

        return $this->success(['message' => 'File deleted successfully']);
    }
}
