<?php

namespace App\Events;

use App\Models\Transfer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransferDeleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Transfer $transfer;
    public string $reason;
    public array $metrics;

    /**
     * Create a new event instance.
     */
    public function __construct(Transfer $transfer, string $reason = 'expired', array $metrics = [])
    {
        $this->transfer = $transfer;
        $this->reason = $reason;
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
            'reason' => $this->reason,
            'file_count' => $this->transfer->files->count() + $this->transfer->transferFiles->count(),
            'total_size' => $this->transfer->total_size,
            'download_count' => $this->transfer->download_count,
            'max_downloads' => $this->transfer->max_downloads,
            'was_password_protected' => $this->transfer->is_password_protected,
            'days_active' => $this->transfer->created_at->diffInDays($this->transfer->updated_at),
            'expired_at' => $this->transfer->expiry_at ?? $this->transfer->expires_at,
            'deleted_at' => now(),
            'metrics' => $this->metrics,
        ];
    }
}
