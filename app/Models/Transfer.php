<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Common\Files\FileEntry;
use Carbon\Carbon;

class Transfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'hash',
        'sender_email',
        'sender_name',
        'recipient_emails',
        'message',
        'password',
        'password_hash',
        'expires_at',
        'expiry_at',
        'download_count',
        'max_downloads',
        'ip_address',
        'user_agent',
        'is_password_protected',
        'total_size',
        'status',
        'completed_at'
    ];

    protected $casts = [
        'recipient_emails' => 'json',
        'expires_at' => 'datetime',
        'expiry_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_password_protected' => 'boolean',
        'total_size' => 'integer',
        'download_count' => 'integer',
        'max_downloads' => 'integer',
    ];

    protected $dates = [
        'expires_at',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    /**
     * Get the legacy files associated with this transfer
     */
    public function files()
    {
        return $this->hasMany(FileEntry::class, 'transfer_id');
    }

    /**
     * Get the TUS transfer files associated with this transfer
     */
    public function transferFiles()
    {
        return $this->hasMany(TransferFile::class, 'transfer_id');
    }

    /**
     * Check if transfer has expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if download limit is reached
     */
    public function isDownloadLimitReached(): bool
    {
        return $this->max_downloads && $this->download_count >= $this->max_downloads;
    }

    /**
     * Check if transfer is accessible
     */
    public function isAccessible(): bool
    {
        return !$this->isExpired() && !$this->isDownloadLimitReached();
    }

    /**
     * Increment download count
     */
    public function incrementDownloadCount(): void
    {
        $this->increment('download_count');
    }

    /**
     * Get human readable file size
     */
    public function getFormattedSizeAttribute(): string
    {
        return $this->formatBytes($this->total_size);
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
     * Generate unique hash
     */
    public static function generateUniqueHash(): string
    {
        do {
            $hash = \Illuminate\Support\Str::random(12);
        } while (self::where('hash', $hash)->exists());
        
        return $hash;
    }

    /**
     * Scope for active transfers
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now())
                    ->orWhereNull('expires_at');
    }

    /**
     * Scope for expired transfers
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Get the share URL for the transfer
     */
    public function getShareUrlAttribute(): string
    {
        return url('/t/' . $this->uuid);
    }

    /**
     * Check if transfer is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if transfer is password protected
     */
    public function isPasswordProtected(): bool
    {
        return !empty($this->password_hash);
    }

    /**
     * Verify password for the transfer
     */
    public function verifyPassword(string $password): bool
    {
        if (!$this->isPasswordProtected()) {
            return true;
        }
        
        return \Illuminate\Support\Facades\Hash::check($password, $this->password_hash);
    }

    /**
     * Generate UUID for new transfers
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($transfer) {
            if (empty($transfer->uuid)) {
                $transfer->uuid = \Illuminate\Support\Str::uuid();
            }
        });
    }
}
