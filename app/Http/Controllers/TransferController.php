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
use Common\Files\Tus\TusServer;
use Common\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class TransferController extends BaseController
{
    public function __construct(protected Request $request, protected FileEntry $entry, protected TusServer $tusServer)
    {        
    }

    /**
     * Handle file uploads via TUS
     */
    public function tusUpload()
    {
        return $this->tusServer->serve();
    }

    /**
     * Create a new file transfer resource
     */
    public function create()
    {
        $this->validate($this->request, [
            'files' => 'required|array',
            'expires_at' => 'nullable|date',
            'password' => 'nullable|string',
        ]);

        $transfer = Transfer::create([
            'hash' => Transfer::generateUniqueHash(),
            'expires_at' => $this->request->input('expires_at') ?? now()->addDays(7),
            'password' => $this->request->input('password'),
            'is_password_protected' => $this->request->has('password'),
        ]);

        foreach ($this->request->file('files') as $file) {
            $payload = new FileEntryPayload([
                'name' => $file->getClientOriginalName(),
                'file_name' => $file->getClientOriginalName(),
                'extension' => $file->getClientOriginalExtension(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
                'hash' => Str::random(32),
                'transfer_id' => $transfer->id,
            ]);

            $fileEntry = app(CreateFileEntry::class)->execute($payload);
            app(StoreFile::class)->execute($payload, ['file' => $file]);

            $transfer->files()->save($fileEntry);
        }

        return $this->success(["transfer" => $transfer]);
    }

    /**
     * Upload multiple files and create transfer
     */
    public function store()
    {
        $this->validate($this->request, [
            'files.*' => [
                'required',
                'file',
                function ($attribute, UploadedFile $value, $fail) {
                    $errors = app(ValidateFileUpload::class)->execute([
                        'extension' => $value->getClientOriginalExtension(),
                        'size' => $value->getSize(),
                    ]);
                    if ($errors) {
                        $fail($errors->first());
                    }
                },
            ],
            'sender_email' => 'nullable|email',
            'sender_name' => 'nullable|string|max:255',
            'recipient_emails' => 'nullable|array',
            'recipient_emails.*' => 'email',
            'message' => 'nullable|string|max:1000',
            'password' => 'nullable|string|min:6',
            'expires_in_days' => 'nullable|integer|min:1|max:30',
            'max_downloads' => 'nullable|integer|min:1',
        ]);

        return DB::transaction(function () {
            // Create transfer
            $transfer = Transfer::create([
                'hash' => Transfer::generateUniqueHash(),
                'sender_email' => $this->request->input('sender_email'),
                'sender_name' => $this->request->input('sender_name'),
                'recipient_emails' => $this->request->input('recipient_emails'),
                'message' => $this->request->input('message'),
                'password' => $this->request->input('password') ? Hash::make($this->request->input('password')) : null,
                'expires_at' => now()->addDays($this->request->input('expires_in_days', 7)),
                'max_downloads' => $this->request->input('max_downloads'),
                'is_password_protected' => $this->request->has('password'),
                'ip_address' => $this->request->ip(),
                'user_agent' => $this->request->userAgent(),
                'status' => 'pending',
            ]);

            $totalSize = 0;
            $files = [];

            // Process each file
            foreach ($this->request->file('files') as $file) {
                $hash = Str::random(32);
                
                $payload = new FileEntryPayload([
                    'name' => $file->getClientOriginalName(),
                    'file_name' => $file->getClientOriginalName(),
                    'extension' => $file->getClientOriginalExtension(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                    'hash' => $hash,
                    'type' => 'file',
                    'public' => true,
                    'workspace_id' => 0,
                    'parent_id' => null,
                    'owner_id' => null,
                    'transfer_id' => $transfer->id,
                ]);

                // Store the file
                app(StoreFile::class)->execute($payload, ['file' => $file]);

                // Create file entry
                $fileEntry = app(CreateFileEntry::class)->execute($payload);
                $fileEntry->update(['hash' => $hash, 'transfer_id' => $transfer->id]);

                event(new FileUploaded($fileEntry));

                $totalSize += $file->getSize();
                $files[] = $fileEntry;
            }

            // Update transfer with total size and mark as completed
            $transfer->update([
                'total_size' => $totalSize,
                'status' => 'completed'
            ]);

            return $this->success([
                'transfer' => $transfer->load('files'),
                'share_url' => url("transfer/{$transfer->hash}"),
                'download_url' => url("api/v1/transfer/{$transfer->hash}/download"),
                'expires_at' => $transfer->expires_at->toISOString(),
                'total_size' => $totalSize,
                'file_count' => count($files),
            ], 201);
        });
    }

    /**
     * Show transfer details
     */
    public function show(string $hash)
    {
        $transfer = Transfer::where('hash', $hash)->with('files')->firstOrFail();
        
        if ($transfer->isExpired()) {
            abort(410, 'Transfer has expired');
        }

        return $this->success([
            'transfer' => $transfer,
            'files' => $transfer->files,
            'download_url' => url("api/v1/transfer/{$hash}/download"),
            'expires_at' => $transfer->expires_at->toISOString(),
            'is_password_protected' => $transfer->is_password_protected,
            'download_count' => $transfer->download_count,
            'total_size' => $transfer->total_size,
        ]);
    }

    /**
     * Verify password for protected transfer
     */
    public function verifyPassword(string $hash)
    {
        $this->validate($this->request, [
            'password' => 'required|string',
        ]);

        $transfer = Transfer::where('hash', $hash)->firstOrFail();
        
        if (!$transfer->is_password_protected) {
            return $this->success(['message' => 'Transfer is not password protected']);
        }

        if (!Hash::check($this->request->input('password'), $transfer->password)) {
            return response()->json(['message' => 'Invalid password'], 401);
        }

        // Generate signed URL for access
        $signedUrl = URL::temporarySignedRoute(
            'transfer.show',
            now()->addHours(24),
            ['hash' => $hash]
        );

        return $this->success([
            'message' => 'Password verified',
            'access_token' => $signedUrl,
        ]);
    }

    /**
     * Download all files from a transfer as ZIP
     */
    public function downloadAll(string $hash)
    {
        $transfer = Transfer::where('hash', $hash)->with('files')->firstOrFail();

        if (!$transfer->isAccessible()) {
            abort(403, 'Transfer expired or download limit reached');
        }

        // Check password if protected
        if ($transfer->is_password_protected && !$this->request->hasValidSignature()) {
            abort(403, 'This transfer is protected by a password');
        }

        // Increment download count
        $transfer->incrementDownloadCount();

        // Create ZIP file
        $zipFileName = 'transfer_' . $transfer->hash . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFileName);
        
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($transfer->files as $file) {
            $filePath = Storage::disk($file->disk)->path($file->disk_prefix);
            if (file_exists($filePath)) {
                $zip->addFile($filePath, $file->name);
            }
        }

        $zip->close();

        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend();
    }

    /**
     * Download individual file from transfer
     */
    public function downloadFile(string $hash, string $fileId, FileResponseFactory $response)
    {
        $transfer = Transfer::where('hash', $hash)->firstOrFail();
        $fileEntry = $transfer->files()->where('id', $fileId)->firstOrFail();

        if (!$transfer->isAccessible()) {
            abort(403, 'Transfer expired or download limit reached');
        }

        // Check password if protected
        if ($transfer->is_password_protected && !$this->request->hasValidSignature()) {
            abort(403, 'This transfer is protected by a password');
        }

        // Increment download count
        $transfer->incrementDownloadCount();

        try {
            return $response->create($fileEntry);
        } catch (FileNotFoundException $e) {
            abort(404, 'File not found');
        }
    }

    /**
     * Get image preview
     */
    public function preview(string $hash, string $fileId)
    {
        $transfer = Transfer::where('hash', $hash)->firstOrFail();
        $fileEntry = $transfer->files()->where('id', $fileId)->firstOrFail();

        if (!$transfer->isAccessible()) {
            abort(403, 'Transfer expired or download limit reached');
        }

        // Check if file is an image
        if (!str_starts_with($fileEntry->mime, 'image/')) {
            abort(400, 'File is not an image');
        }

        try {
            $filePath = Storage::disk($fileEntry->disk)->path($fileEntry->disk_prefix);
            return response()->file($filePath);
        } catch (FileNotFoundException $e) {
            abort(404, 'File not found');
        }
    }

    /**
     * Delete transfer
     */
    public function destroy(string $hash)
    {
        $transfer = Transfer::where('hash', $hash)->firstOrFail();
        
        // Delete physical files
        foreach ($transfer->files as $file) {
            if ($file->disk_prefix) {
                Storage::disk($file->disk)->delete($file->disk_prefix);
            }
        }
        
        // Delete transfer and its files
        $transfer->delete();

        return $this->success(['message' => 'Transfer deleted successfully']);
    }
}
