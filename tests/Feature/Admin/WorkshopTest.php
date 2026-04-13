<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class WorkshopTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_admin_workshops_index(): void
    {
        $this->get(route('admin.workshops.index'))
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_non_admin_users_cannot_access_admin_workshops_index(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $this->actingAs($user)
            ->get(route('admin.workshops.index'))
            ->assertForbidden();
    }

    public function test_admin_users_can_access_admin_workshops_index(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workshops.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Workshops/Index')
                ->has('workshopStatistics')
                ->where('workshopStatistics.total_active_registrations', 0)
                ->where('workshopStatistics.most_popular_workshop', null));
    }

    public function test_admin_workshops_index_statistics_reflect_registrations(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $wA = Workshop::factory()->upcoming()->create(['capacity' => 10]);
        $wB = Workshop::factory()->upcoming()->create(['capacity' => 10]);

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $u3 = User::factory()->create();

        WorkshopRegistration::factory()->create([
            'workshop_id' => $wA->id,
            'user_id' => $u1->id,
            'cancelled_at' => null,
        ]);
        WorkshopRegistration::factory()->create([
            'workshop_id' => $wB->id,
            'user_id' => $u2->id,
            'cancelled_at' => null,
        ]);
        WorkshopRegistration::factory()->create([
            'workshop_id' => $wB->id,
            'user_id' => $u3->id,
            'cancelled_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workshops.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Workshops/Index')
                ->where('workshopStatistics.total_active_registrations', 3)
                ->where('workshopStatistics.most_popular_workshop.id', $wB->id)
                ->where('workshopStatistics.most_popular_workshop.active_registrations_count', 2));
    }

    public function test_admin_can_create_workshop(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $startsAt = now()->addWeek()->seconds(0);

        $this->actingAs($admin)
            ->post(route('admin.workshops.store'), [
                'name' => 'Intro to Testing',
                'slug' => '',
                'description' => 'Learn PHPUnit basics.',
                'starts_at' => $startsAt->toIso8601String(),
                'duration_minutes' => 90,
                'capacity' => 12,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('workshops', [
            'name' => 'Intro to Testing',
            'slug' => 'intro-to-testing',
            'capacity' => 12,
        ]);
    }

    public function test_non_admin_cannot_create_workshop(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $startsAt = now()->addWeek();

        $this->actingAs($user)
            ->post(route('admin.workshops.store'), [
                'name' => 'Blocked',
                'slug' => 'blocked',
                'description' => null,
                'starts_at' => $startsAt->toIso8601String(),
                'duration_minutes' => 60,
                'capacity' => 5,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_delete_workshop(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $workshop = Workshop::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.workshops.destroy', $workshop))
            ->assertRedirect(route('admin.workshops.index', absolute: false));

        $this->assertDatabaseMissing('workshops', [
            'id' => $workshop->id,
        ]);
    }
}
