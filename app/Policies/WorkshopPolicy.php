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
        if ($workshop->starts_at->lte(now())) {
            return Response::deny('This workshop has already started.');
        }

        return Response::allow();
    }

    public function cancelRegistration(User $user, Workshop $workshop): Response
    {
        if ($workshop->starts_at->lte(now())) {
            return Response::deny('You can only cancel before the workshop starts.');
        }

        return Response::allow();
    }
}
