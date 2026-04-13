<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Auth\Access\Response;

class WorkshopPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Workshop $workshop): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, Workshop $workshop): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, Workshop $workshop): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function register(User $user, Workshop $workshop): Response
    {
        if (! $workshop->starts_at->isFuture()) {
            return Response::deny('Registration is closed; this workshop is no longer upcoming.');
        }

        return Response::allow();
    }

    public function cancelRegistration(User $user, Workshop $workshop): Response
    {
        if (! $workshop->starts_at->isFuture()) {
            return Response::deny('You can only cancel before the workshop starts.');
        }

        return Response::allow();
    }

    public function joinWaitlist(User $user, Workshop $workshop): Response
    {
        if (! $workshop->starts_at->isFuture()) {
            return Response::deny('Registration is closed; this workshop is no longer upcoming.');
        }

        if ($workshop->activeRegistrations()->count() < $workshop->capacity) {
            return Response::deny('This workshop is not full; register for a spot instead.');
        }

        return Response::allow();
    }

    public function leaveWaitlist(User $user, Workshop $workshop): Response
    {
        if (! $workshop->starts_at->isFuture()) {
            return Response::deny('You can only leave the waiting list before the workshop starts.');
        }

        return Response::allow();
    }
}
