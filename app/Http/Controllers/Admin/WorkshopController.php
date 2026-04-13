<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkshopRequest;
use App\Http\Requests\UpdateWorkshopRequest;
use App\Models\Workshop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WorkshopController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Workshop::class);

        $workshops = Workshop::query()
            ->withCount([
                'registrations as active_registrations_count' => function ($query): void {
                    $query->whereNull('cancelled_at');
                },
            ])
            ->orderByDesc('starts_at')
            ->paginate(15)
            ->through(function (Workshop $workshop): array {
                return [
                    'id' => $workshop->id,
                    'name' => $workshop->name,
                    'slug' => $workshop->slug,
                    'starts_at' => $workshop->starts_at->toIso8601String(),
                    'duration_minutes' => $workshop->duration_minutes,
                    'capacity' => $workshop->capacity,
                    'active_registrations_count' => $workshop->active_registrations_count,
                ];
            });

        return Inertia::render('Admin/Workshops/Index', [
            'workshops' => $workshops,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Workshop::class);

        return Inertia::render('Admin/Workshops/Create');
    }

    public function store(StoreWorkshopRequest $request): RedirectResponse
    {
        $this->authorize('create', Workshop::class);

        $data = $request->validated();

        if (blank($data['slug'] ?? null)) {
            unset($data['slug']);
        } elseif (is_string($data['slug'])) {
            $data['slug'] = Str::slug($data['slug']);
        }

        $workshop = Workshop::query()->create($data);

        return redirect()->route('admin.workshops.edit', $workshop);
    }

    public function show(Workshop $workshop): Response
    {
        $this->authorize('view', $workshop);

        $workshop->load([
            'registrations' => function ($query): void {
                $query->with('user:id,name,email')->orderByDesc('created_at');
            },
        ]);

        $workshop->loadCount([
            'registrations as active_registrations_count' => function ($query): void {
                $query->whereNull('cancelled_at');
            },
        ]);

        return Inertia::render('Admin/Workshops/Show', [
            'workshop' => [
                'id' => $workshop->id,
                'name' => $workshop->name,
                'slug' => $workshop->slug,
                'description' => $workshop->description,
                'starts_at' => $workshop->starts_at->toIso8601String(),
                'duration_minutes' => $workshop->duration_minutes,
                'capacity' => $workshop->capacity,
                'active_registrations_count' => $workshop->active_registrations_count,
                'registrations' => $workshop->registrations->map(function ($registration): array {
                    return [
                        'id' => $registration->id,
                        'user' => [
                            'id' => $registration->user->id,
                            'name' => $registration->user->name,
                            'email' => $registration->user->email,
                        ],
                        'cancelled_at' => $registration->cancelled_at?->toIso8601String(),
                        'created_at' => $registration->created_at->toIso8601String(),
                    ];
                }),
            ],
        ]);
    }

    public function edit(Workshop $workshop): Response
    {
        $this->authorize('update', $workshop);

        return Inertia::render('Admin/Workshops/Edit', [
            'workshop' => [
                'id' => $workshop->id,
                'name' => $workshop->name,
                'slug' => $workshop->slug,
                'description' => $workshop->description,
                'starts_at' => $workshop->starts_at->format('Y-m-d\TH:i'),
                'duration_minutes' => $workshop->duration_minutes,
                'capacity' => $workshop->capacity,
            ],
        ]);
    }

    public function update(UpdateWorkshopRequest $request, Workshop $workshop): RedirectResponse
    {
        $this->authorize('update', $workshop);

        $data = $request->validated();
        $data['slug'] = Str::slug($data['slug']);

        $workshop->fill($data);
        $workshop->save();

        return redirect()->route('admin.workshops.edit', $workshop);
    }

    public function destroy(Workshop $workshop): RedirectResponse
    {
        $this->authorize('delete', $workshop);

        $workshop->delete();

        return redirect()->route('admin.workshops.index');
    }
}
