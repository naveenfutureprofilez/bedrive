<?php

namespace App\Http\Controllers;

use Common\Core\BaseController;
use Common\Files\FileEntry;
use Common\Files\S3\GenerateSignedUrlForThumbnail;
use Illuminate\Http\Request;

class ThumbnailController extends BaseController
{
    public function __construct(
        protected Request $request,
        protected FileEntry $fileEntry
    ) {
    }

    /**
     * Get thumbnail URL for a file entry
     */
    public function show(string $hash)
    {
        $entry = $this->fileEntry->where('hash', $hash)->firstOrFail();
        
        // Check if file is an image
        if (!str_starts_with($entry->mime, 'image/')) {
            return $this->error('File is not an image', 400);
        }

        $thumbnailGenerator = app(GenerateSignedUrlForThumbnail::class);
        $thumbnailUrl = $thumbnailGenerator->execute($entry);

        if (!$thumbnailUrl) {
            return $this->error('Thumbnail not available', 404);
        }

        return $this->success([
            'thumbnail_url' => $thumbnailUrl,
            'expires_at' => now()->addHour()->toISOString(),
        ]);
    }

    /**
     * Get thumbnail URLs for multiple files
     */
    public function batch()
    {
        $this->validate($this->request, [
            'hashes' => 'required|array',
            'hashes.*' => 'required|string',
        ]);

        $hashes = $this->request->get('hashes');
        $entries = $this->fileEntry->whereIn('hash', $hashes)->get();
        
        $thumbnailGenerator = app(GenerateSignedUrlForThumbnail::class);
        $thumbnails = [];

        foreach ($entries as $entry) {
            $thumbnailUrl = null;
            
            if (str_starts_with($entry->mime, 'image/')) {
                $thumbnailUrl = $thumbnailGenerator->execute($entry);
            }

            $thumbnails[$entry->hash] = [
                'hash' => $entry->hash,
                'name' => $entry->name,
                'mime' => $entry->mime,
                'thumbnail_url' => $thumbnailUrl,
                'is_image' => str_starts_with($entry->mime, 'image/'),
            ];
        }

        return $this->success([
            'thumbnails' => $thumbnails,
            'expires_at' => now()->addHour()->toISOString(),
        ]);
    }
}
