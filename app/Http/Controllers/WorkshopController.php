<?php

namespace App\Http\Controllers;

use App\Models\Workshop;
use App\Models\WorkshopWaitlistEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class WorkshopController extends Controller
{
    public function index(): Response
    {
        $mapper = function (Workshop $workshop): array {
            return [
                'id' => $workshop->id,
                'name' => $workshop->name,
                'slug' => $workshop->slug,
                'description' => $workshop->description,
                'starts_at' => $workshop->starts_at->toIso8601String(),
                'duration_minutes' => $workshop->duration_minutes,
                'capacity' => $workshop->capacity,
                'active_registrations_count' => $workshop->active_registrations_count,
                'remaining_spots' => max(0, $workshop->capacity - (int) $workshop->active_registrations_count),
            ];
        };

        $workshops = Workshop::query()
            ->where('starts_at', '>=', now())
            ->withCount([
                'registrations as active_registrations_count' => function ($query): void {
                    $query->whereNull('cancelled_at');
                },
            ])
            ->orderBy('starts_at')
            ->paginate(12)
            ->through($mapper);

        $pastWorkshops = Workshop::query()
            ->where('starts_at', '<', now())
            ->withCount([
                'registrations as active_registrations_count' => function ($query): void {
                    $query->whereNull('cancelled_at');
                },
            ])
            ->orderByDesc('starts_at')
            ->limit(20)
            ->get()
            ->map(fn (Workshop $workshop): array => $mapper($workshop));

        return Inertia::render('Workshops/Index', [
            'workshops' => $workshops,
            'pastWorkshops' => $pastWorkshops,
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
        ]);
    }

    public function show(Request $request, Workshop $workshop): Response
    {
        $workshop->loadCount([
            'registrations as active_registrations_count' => function ($query): void {
                $query->whereNull('cancelled_at');
            },
        ]);

        $isRegistered = false;
        $isOnWaitlist = false;
        $waitlistPosition = null;

        if ($request->user()) {
            $isRegistered = $workshop->registrations()
                ->where('user_id', $request->user()->id)
                ->whereNull('cancelled_at')
                ->exists();

            $waitlistEntry = WorkshopWaitlistEntry::query()
                ->where('workshop_id', $workshop->id)
                ->where('user_id', $request->user()->id)
                ->first();

            $isOnWaitlist = $waitlistEntry !== null;

            if ($waitlistEntry !== null) {
                $waitlistPosition = 1 + WorkshopWaitlistEntry::query()
                    ->where('workshop_id', $workshop->id)
                    ->where(function ($query) use ($waitlistEntry): void {
                        $query->where('created_at', '<', $waitlistEntry->created_at)
                            ->orWhere(function ($query) use ($waitlistEntry): void {
                                $query->where('created_at', '=', $waitlistEntry->created_at)
                                    ->where('id', '<', $waitlistEntry->id);
                            });
                    })
                    ->count();
            }
        }

        $isFull = (int) $workshop->active_registrations_count >= (int) $workshop->capacity;

        return Inertia::render('Workshops/Show', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'registrationOpen' => $workshop->starts_at->isFuture(),
            'workshop' => [
                'id' => $workshop->id,
                'name' => $workshop->name,
                'slug' => $workshop->slug,
                'description' => $workshop->description,
                'starts_at' => $workshop->starts_at->toIso8601String(),
                'duration_minutes' => $workshop->duration_minutes,
                'capacity' => $workshop->capacity,
                'active_registrations_count' => $workshop->active_registrations_count,
                'remaining_spots' => max(0, $workshop->capacity - (int) $workshop->active_registrations_count),
            ],
            'isRegistered' => $isRegistered,
            'isOnWaitlist' => $isOnWaitlist,
            'waitlistPosition' => $waitlistPosition,
            'canJoinWaitlist' => $request->user() !== null
                && ! $isRegistered
                && ! $isOnWaitlist
                && $isFull
                && $workshop->starts_at->isFuture()
                && ! $workshop->userWouldOverlapActiveRegistrations($request->user()),
        ]);
    }
}
