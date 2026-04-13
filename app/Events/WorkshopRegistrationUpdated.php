<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkshopRegistrationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $workshopId,
        public int $activeRegistrationsCount,
        public int $remainingSpots,
        public int $capacity,
        public ?int $promotedUserId = null,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('workshop.'.$this->workshopId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'WorkshopRegistrationUpdated';
    }

    /**
     * @return array<string, int|null>
     */
    public function broadcastWith(): array
    {
        return [
            'workshop_id' => $this->workshopId,
            'active_registrations_count' => $this->activeRegistrationsCount,
            'remaining_spots' => $this->remainingSpots,
            'capacity' => $this->capacity,
            'promoted_user_id' => $this->promotedUserId,
        ];
    }
}
