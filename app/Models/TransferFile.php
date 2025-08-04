<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TransferFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_id',
        'original_name',
        'size',
        'mime_type',
        'storage_path',
        'upload_key',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    /**
     * Get the transfer that owns this file
     */
    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }

    /**
     * Get human readable file size
     */
    public function getFormattedSizeAttribute(): string
    {
        return $this->formatBytes($this->size);
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Get the download URL for this file
     */
    public function getDownloadUrlAttribute(): string
    {
        return route('transfer.download', [
            'uuid' => $this->transfer->uuid,
            'fileId' => $this->id
        ]);
    }

    /**
     * Check if file exists in storage
     */
    public function exists(): bool
    {
        if (!$this->storage_path) {
            return false;
        }
        
        $disk = Storage::disk(config('tus.disk', 'uploads'));
        return $disk->exists($this->storage_path);
    }

    /**
     * Get file content from storage
     */
    public function getContent()
    {
        if (!$this->exists()) {
            return null;
        }
        
        $disk = Storage::disk(config('tus.disk', 'uploads'));
        return $disk->get($this->storage_path);
    }

    /**
     * Get file stream from storage
     */
    public function getStream()
    {
        if (!$this->exists()) {
            return null;
        }
        
        $disk = Storage::disk(config('tus.disk', 'uploads'));
        return $disk->readStream($this->storage_path);
    }
}
