<?php

namespace App\Http\Controllers;

use App\Models\Workshop;
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
        if ($request->user()) {
            $isRegistered = $workshop->registrations()
                ->where('user_id', $request->user()->id)
                ->whereNull('cancelled_at')
                ->exists();
        }

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
        ]);
    }
}
