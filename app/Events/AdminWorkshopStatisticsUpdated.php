<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminWorkshopStatisticsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array{
     *     most_popular_workshop: array{id: int, name: string, slug: string, active_registrations_count: int}|null,
     *     total_active_registrations: int
     * }  $statistics
     */
    public function __construct(public array $statistics) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.workshop-statistics'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'AdminWorkshopStatisticsUpdated';
    }

    /**
     * @return array{
     *     most_popular_workshop: array{id: int, name: string, slug: string, active_registrations_count: int}|null,
     *     total_active_registrations: int
     * }
     */
    public function broadcastWith(): array
    {
        return $this->statistics;
    }
}
