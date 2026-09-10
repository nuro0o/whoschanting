<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class RoomUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public function __construct(public int $roomId, public int $revision) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('room.'.$this->roomId);
    }

    public function broadcastAs(): string
    {
        return 'room.updated';
    }

    /** @return array<string, int> */
    public function broadcastWith(): array
    {
        return ['revision' => $this->revision];
    }
}
