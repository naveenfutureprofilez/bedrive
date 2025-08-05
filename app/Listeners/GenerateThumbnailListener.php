<?php

namespace App\Listeners;

use App\Jobs\GenerateThumbnailJob;
use Common\Files\Events\FileUploaded;
use Illuminate\Support\Facades\Log;

class GenerateThumbnailListener
{
    /**
     * Handle the event.
     */
    public function handle(FileUploaded $event): void
    {
        $fileEntry = $event->fileEntry;
        
        // Only generate thumbnails for image files
        if (!$this->isImageFile($fileEntry)) {
            return;
        }

        // Only generate thumbnails for files stored on S3/R2
        if ($fileEntry->disk !== 'uploads') {
            return;
        }

        Log::info("Dispatching thumbnail generation job for file: {$fileEntry->name}");
        
        // Dispatch the thumbnail generation job
        GenerateThumbnailJob::dispatch($fileEntry)
            ->delay(now()->addSeconds(10)); // Small delay to ensure file is fully uploaded
    }

    /**
     * Check if the file is an image that should have a thumbnail generated
     */
    private function isImageFile($fileEntry): bool
    {
        return str_starts_with($fileEntry->mime, 'image/') && 
               in_array(strtolower($fileEntry->extension), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    }
}
