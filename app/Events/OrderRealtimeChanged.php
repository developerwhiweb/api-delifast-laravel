<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class OrderRealtimeChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly string $eventId;

    public readonly int $tenantId;

    public readonly int $orderId;

    public readonly string $status;

    public readonly string $eventType;

    public readonly ?string $updatedAt;

    public function __construct(int $tenantId, int $orderId, string $status, string $eventType, ?string $updatedAt)
    {
        $this->eventId = (string) Str::uuid();
        $this->tenantId = $tenantId;
        $this->orderId = $orderId;
        $this->status = $status;
        $this->eventType = $eventType;
        $this->updatedAt = $updatedAt;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenants.{$this->tenantId}.orders")];
    }

    public function broadcastAs(): string
    {
        return 'orders.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'order_id' => $this->orderId,
            'status' => $this->status,
            'updated_at' => $this->updatedAt,
        ];
    }
}
