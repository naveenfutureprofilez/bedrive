<?php

namespace App\Jobs;

use Common\Files\FileEntry;
use Common\Files\S3\InteractsWithS3Api;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class GenerateThumbnailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, InteractsWithS3Api;

    protected FileEntry $fileEntry;
    protected int $maxWidth;
    protected int $maxHeight;
    protected int $quality;

    public function __construct(
        FileEntry $fileEntry, 
        int $maxWidth = 400, 
        int $maxHeight = 400, 
        int $quality = 80
    ) {
        $this->fileEntry = $fileEntry;
        $this->maxWidth = $maxWidth;
        $this->maxHeight = $maxHeight;
        $this->quality = $quality;
    }

    public function handle(): void
    {
        try {
            // Only process image files
            if (!$this->isImageFile($this->fileEntry)) {
                Log::info("Skipping thumbnail generation for non-image file: {$this->fileEntry->name}");
                return;
            }

            $this->generateThumbnail();
            
            // Mark the file entry as having a thumbnail
            $this->fileEntry->update(['thumbnail' => true]);
            
            Log::info("Thumbnail generated successfully for file: {$this->fileEntry->name}");
            
        } catch (\Exception $e) {
            Log::error("Failed to generate thumbnail for file {$this->fileEntry->name}: " . $e->getMessage());
            throw $e;
        }
    }

    protected function generateThumbnail(): void
    {
        // Download the original image from S3
        $originalImageData = $this->downloadOriginalImage();
        
        if (!$originalImageData) {
            throw new \Exception('Failed to download original image from storage');
        }

        // Create thumbnail using Intervention Image
        $manager = new ImageManager(new Driver());
        $image = $manager->read($originalImageData);
        
        // Resize image maintaining aspect ratio
        $image->scaleDown($this->maxWidth, $this->maxHeight);
        
        // Determine output format
        $outputFormat = $this->getOutputFormat();
        
        // Encode the thumbnail
        $thumbnailData = $image->encode($outputFormat, $this->quality);
        
        // Upload thumbnail to S3
        $this->uploadThumbnail($thumbnailData->toString());
    }

    protected function downloadOriginalImage(): ?string
    {
        try {
            $result = $this->getClient()->getObject([
                'Bucket' => $this->getBucket(),
                'Key' => $this->fileEntry->disk_prefix,
            ]);
            
            return $result['Body']->getContents();
        } catch (\Exception $e) {
            Log::error("Failed to download original image: " . $e->getMessage());
            return null;
        }
    }

    protected function uploadThumbnail(string $thumbnailData): void
    {
        $thumbnailKey = $this->getThumbnailKey();
        
        $this->getClient()->putObject([
            'Bucket' => $this->getBucket(),
            'Key' => $thumbnailKey,
            'Body' => $thumbnailData,
            'ContentType' => $this->getOutputMimeType(),
            'ACL' => $this->getAcl(),
        ]);
    }

    protected function getThumbnailKey(): string
    {
        $originalKey = $this->fileEntry->disk_prefix;
        $pathInfo = pathinfo($originalKey);
        
        // Generate thumbnail key: path/filename_thumb.jpg
        $thumbnailKey = $pathInfo['dirname'] . '/' . 
                       $pathInfo['filename'] . '_thumb.' . 
                       ($this->fileEntry->extension === 'png' ? 'png' : 'jpg');
        
        return ltrim($thumbnailKey, '/');
    }

    protected function getOutputFormat(): string
    {
        // Use PNG for PNG files to preserve transparency, JPEG for others
        return $this->fileEntry->extension === 'png' ? 'png' : 'jpeg';
    }

    protected function getOutputMimeType(): string
    {
        return $this->fileEntry->extension === 'png' ? 'image/png' : 'image/jpeg';
    }

    protected function isImageFile(FileEntry $entry): bool
    {
        return str_starts_with($entry->mime, 'image/') && 
               in_array(strtolower($entry->extension), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Thumbnail generation job failed for file {$this->fileEntry->name}: " . $exception->getMessage());
    }
}
