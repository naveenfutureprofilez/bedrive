<?php

namespace App\Http\Controllers;

use Common\Core\BaseController;
use Common\Files\FileEntry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DownloadPageController extends BaseController
{
    public function __construct(
        private FileEntry $fileEntry,
        private Request $request
    ) {
    }

    /**
     * Get download page information by slug
     */
    public function show(string $slug): JsonResponse
    {
        // For now, this is a placeholder implementation
        // In a real implementation, you would:
        // 1. Look up the slug in a downloads table
        // 2. Verify permissions and expiration
        // 3. Return file information
        
        // Sample implementation using existing file entries
        // This would be replaced with proper slug-based lookup
        $entries = $this->fileEntry
            ->where('name', 'like', "%{$slug}%")
            ->orWhere('hash', $slug)
            ->limit(5)
            ->get();

        if ($entries->isEmpty()) {
            return $this->error('Download not found', 404);
        }

        $files = $entries->map(function ($entry) {
            return [
                'id' => $entry->id,
                'name' => $entry->getNameWithExtension(),
                'size' => $entry->file_size ?? 0,
                'mime' => $entry->mime ?? 'application/octet-stream',
                'hash' => $entry->hash,
            ];
        });

        $totalSize = $files->sum('size');

        return $this->success([
            'id' => $slug,
            'slug' => $slug,
            'files' => $files,
            'totalSize' => $totalSize,
            'allowDownload' => true,
            'expiresAt' => now()->addDays(7)->toISOString(),
            'title' => "Download: {$slug}",
        ]);
    }

    /**
     * Create a new download page (for testing purposes)
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'title' => 'string|max:255',
            'file_hashes' => 'required|array',
            'file_hashes.*' => 'required|string',
            'expires_at' => 'nullable|date',
        ]);

        $hashes = $request->input('file_hashes');
        $entries = $this->fileEntry->whereIn('hash', $hashes)->get();

        if ($entries->isEmpty()) {
            return $this->error('No valid files found', 404);
        }

        $files = $entries->map(function ($entry) {
            return [
                'id' => $entry->id,
                'name' => $entry->getNameWithExtension(),
                'size' => $entry->file_size ?? 0,
                'mime' => $entry->mime ?? 'application/octet-stream',
                'hash' => $entry->hash,
            ];
        });

        $totalSize = $files->sum('size');
        $slug = uniqid('dl_');

        // In a real implementation, you would save this to a downloads table
        // For now, we'll just return the data

        return $this->success([
            'id' => $slug,
            'slug' => $slug,
            'files' => $files,
            'totalSize' => $totalSize,
            'allowDownload' => true,
            'expiresAt' => $request->input('expires_at', now()->addDays(7)->toISOString()),
            'title' => $request->input('title', "Download Package"),
            'url' => url("d/{$slug}"),
        ], 201);
    }
}
