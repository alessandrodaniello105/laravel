<?php

namespace App\Http\Controllers;

use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        \assert($user !== null);

        $workshopIds = WorkshopRegistration::query()
            ->where('user_id', $user->id)
            ->whereNull('cancelled_at')
            ->pluck('workshop_id');

        $registeredWorkshops = Workshop::query()
            ->whereIn('id', $workshopIds)
            ->where('starts_at', '>=', now())
            ->withCount([
                'registrations as active_registrations_count' => function ($query): void {
                    $query->whereNull('cancelled_at');
                },
            ])
            ->orderBy('starts_at')
            ->get()
            ->map(static function (Workshop $workshop): array {
                return [
                    'id' => $workshop->id,
                    'name' => $workshop->name,
                    'slug' => $workshop->slug,
                    'starts_at' => $workshop->starts_at->toIso8601String(),
                    'duration_minutes' => $workshop->duration_minutes,
                    'capacity' => $workshop->capacity,
                    'active_registrations_count' => $workshop->active_registrations_count,
                    'remaining_spots' => max(0, $workshop->capacity - (int) $workshop->active_registrations_count),
                ];
            });

        return Inertia::render('Dashboard', [
            'registeredWorkshops' => $registeredWorkshops,
        ]);
    }
}
