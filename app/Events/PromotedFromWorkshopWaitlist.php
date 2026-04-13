<?php

namespace App\Events;

use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a user is moved from the FIFO waitlist into an active registration.
 * Hook here later for toast + email notifications (not implemented yet).
 */
class PromotedFromWorkshopWaitlist
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public Workshop $workshop,
    ) {}
}
