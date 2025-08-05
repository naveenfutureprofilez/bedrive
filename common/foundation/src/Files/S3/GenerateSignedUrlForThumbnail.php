<?php

namespace Common\Files\S3;

use Carbon\Carbon;
use Common\Files\FileEntry;
use Common\Files\S3\InteractsWithS3Api;

class GenerateSignedUrlForThumbnail
{
    use InteractsWithS3Api;

    /**
     * Generate a signed URL for thumbnail preview
     */
    public function execute(FileEntry $entry, int $expirationMinutes = 60): ?string
    {
        if (!$this->shouldGenerateThumbnailUrl($entry)) {
            return null;
        }

        $thumbnailKey = $this->getThumbnailKey($entry);
        
        // Check if thumbnail exists in storage
        if (!$this->thumbnailExists($thumbnailKey)) {
            return null;
        }

        $command = $this->getClient()->getCommand('GetObject', [
            'Bucket' => $this->getBucket(),
            'Key' => $thumbnailKey,
        ]);

        $presignedRequest = $this->getClient()->createPresignedRequest(
            $command,
            Carbon::now()->addMinutes($expirationMinutes)
        );

        return (string) $presignedRequest->getUri();
    }

    /**
     * Check if we should generate thumbnail URL for this file
     */
    private function shouldGenerateThumbnailUrl(FileEntry $entry): bool
    {
        // Only generate thumbnails for images
        return str_starts_with($entry->mime, 'image/') && 
               in_array(strtolower($entry->extension), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    }

    /**
     * Get the thumbnail key for storage
     */
    private function getThumbnailKey(FileEntry $entry): string
    {
        $originalKey = $entry->disk_prefix;
        $pathInfo = pathinfo($originalKey);
        
        // Generate thumbnail key: path/filename_thumb.jpg
        $thumbnailKey = $pathInfo['dirname'] . '/' . 
                       $pathInfo['filename'] . '_thumb.' . 
                       ($entry->extension === 'png' ? 'png' : 'jpg');
        
        return ltrim($thumbnailKey, '/');
    }

    /**
     * Check if thumbnail exists in storage
     */
    private function thumbnailExists(string $thumbnailKey): bool
    {
        try {
            $this->getClient()->headObject([
                'Bucket' => $this->getBucket(),
                'Key' => $thumbnailKey,
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
