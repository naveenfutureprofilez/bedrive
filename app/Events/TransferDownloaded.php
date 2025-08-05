<?php

namespace App\Events;

use App\Models\Transfer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransferDownloaded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Transfer $transfer;
    public array $metrics;

    /**
     * Create a new event instance.
     */
    public function __construct(Transfer $transfer, array $metrics = [])
    {
        $this->transfer = $transfer;
        $this->metrics = $metrics;
    }

    /**
     * Get the data for analytics.
     */
    public function getAnalyticsData(): array
    {
        return [
            'transfer_uuid' => $this->transfer->uuid,
            'transfer_hash' => $this->transfer->hash,
            'file_count' => $this->transfer->files->count() + $this->transfer->transferFiles->count(),
            'total_size' => $this->transfer->total_size,
            'download_count' => $this->transfer->download_count,
            'max_downloads' => $this->transfer->max_downloads,
            'was_password_protected' => $this->transfer->is_password_protected,
            'days_since_created' => $this->transfer->created_at->diffInDays(now()),
            'downloaded_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metrics' => $this->metrics,
        ];
    }
}
