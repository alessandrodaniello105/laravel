<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Models\WorkshopWaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_dashboard_includes_upcoming_registered_workshops(): void
    {
        $user = User::factory()->create();
        $workshop = Workshop::factory()->upcoming()->create([
            'name' => 'Alpha Session',
        ]);

        WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
            'cancelled_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->has('registeredWorkshops', 1)
                ->where('registeredWorkshops.0.name', 'Alpha Session')
                ->has('waitlistedWorkshops', 0)
            );
    }

    public function test_dashboard_excludes_past_workshops_even_when_registered(): void
    {
        $user = User::factory()->create();
        $workshop = Workshop::factory()->past()->create();

        WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->has('registeredWorkshops', 0)
                ->has('waitlistedWorkshops', 0)
            );
    }

    public function test_dashboard_reflects_counts_after_registering_for_workshop(): void
    {
        $user = User::factory()->create();
        $workshop = Workshop::factory()->upcoming()->create([
            'capacity' => 10,
        ]);

        $this->actingAs($user)
            ->post(route('workshops.register', $workshop))
            ->assertRedirect(route('workshops.show', $workshop, absolute: false));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->has('registeredWorkshops', 1)
                ->where('registeredWorkshops.0.id', $workshop->id)
                ->where('registeredWorkshops.0.active_registrations_count', 1)
                ->where('registeredWorkshops.0.remaining_spots', 9)
                ->has('waitlistedWorkshops', 0)
            );
    }

    public function test_dashboard_excludes_cancelled_registrations(): void
    {
        $user = User::factory()->create();
        $workshop = Workshop::factory()->upcoming()->create();

        WorkshopRegistration::factory()->cancelled()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->has('registeredWorkshops', 0)
                ->has('waitlistedWorkshops', 0)
            );
    }

    public function test_dashboard_includes_upcoming_waitlisted_workshops_with_position(): void
    {
        $user = User::factory()->create();
        $workshop = Workshop::factory()->upcoming()->create([
            'name' => 'Full Session',
            'capacity' => 1,
        ]);

        WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => User::factory(),
            'cancelled_at' => null,
        ]);

        WorkshopWaitlistEntry::query()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->has('registeredWorkshops', 0)
                ->has('waitlistedWorkshops', 1)
                ->where('waitlistedWorkshops.0.name', 'Full Session')
                ->where('waitlistedWorkshops.0.waitlist_position', 1)
            );
    }
}
