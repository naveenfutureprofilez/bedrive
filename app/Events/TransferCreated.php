<?php

namespace App\Events;

use App\Models\Transfer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransferCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Transfer $transfer;

    /**
     * Create a new event instance.
     */
    public function __construct(Transfer $transfer)
    {
        $this->transfer = $transfer;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('transfers'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'transfer' => [
                'uuid' => $this->transfer->uuid,
                'total_size' => $this->transfer->total_size,
                'status' => $this->transfer->status,
                'share_url' => $this->transfer->share_url,
                'file_count' => $this->transfer->transferFiles->count(),
            ],
        ];
    }
}
