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
                ->where('workshopStatistics.most_popular_workshop', null)
                ->where('workshopStatistics.most_popular_all_time_workshop', null));
    }

    public function test_admin_workshops_index_statistics_reflect_registrations(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $wSameA = Workshop::factory()->upcoming()->create([
            'name' => 'Shared workshop title',
            'slug' => 'shared-workshop-title-a',
            'capacity' => 10,
        ]);
        $wSameB = Workshop::factory()->upcoming()->create([
            'name' => 'Shared workshop title',
            'slug' => 'shared-workshop-title-b',
            'capacity' => 10,
        ]);
        $wOther = Workshop::factory()->upcoming()->create([
            'name' => 'Different title',
            'slug' => 'different-title',
            'capacity' => 10,
        ]);

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $u3 = User::factory()->create();
        $u4 = User::factory()->create();
        $u5 = User::factory()->create();
        $u6 = User::factory()->create();

        WorkshopRegistration::factory()->create([
            'workshop_id' => $wSameA->id,
            'user_id' => $u1->id,
            'cancelled_at' => null,
        ]);
        WorkshopRegistration::factory()->cancelled()->create([
            'workshop_id' => $wSameA->id,
            'user_id' => $u4->id,
        ]);
        WorkshopRegistration::factory()->cancelled()->create([
            'workshop_id' => $wSameA->id,
            'user_id' => $u5->id,
        ]);
        WorkshopRegistration::factory()->create([
            'workshop_id' => $wSameB->id,
            'user_id' => $u6->id,
            'cancelled_at' => null,
        ]);
        WorkshopRegistration::factory()->create([
            'workshop_id' => $wOther->id,
            'user_id' => $u2->id,
            'cancelled_at' => null,
        ]);
        WorkshopRegistration::factory()->create([
            'workshop_id' => $wOther->id,
            'user_id' => $u3->id,
            'cancelled_at' => null,
        ]);

        $representativeId = min($wSameA->id, $wSameB->id);

        $this->actingAs($admin)
            ->get(route('admin.workshops.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Workshops/Index')
                ->where('workshopStatistics.total_active_registrations', 4)
                ->where('workshopStatistics.most_popular_workshop.id', $wOther->id)
                ->where('workshopStatistics.most_popular_workshop.active_registrations_count', 2)
                ->where('workshopStatistics.most_popular_all_time_workshop.id', $representativeId)
                ->where('workshopStatistics.most_popular_all_time_workshop.name', 'Shared workshop title')
                ->where('workshopStatistics.most_popular_all_time_workshop.total_registrations_count', 4));
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

    public function test_admin_cannot_edit_past_workshop(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $workshop = Workshop::factory()->past()->create([
            'name' => 'Archived session',
            'slug' => 'archived-session',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workshops.edit', $workshop))
            ->assertForbidden();
    }

    public function test_admin_cannot_update_past_workshop(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $workshop = Workshop::factory()->past()->create([
            'name' => 'Archived session',
            'slug' => 'archived-session-two',
        ]);

        $startsAt = now()->addWeek()->seconds(0);

        $this->actingAs($admin)
            ->put(route('admin.workshops.update', $workshop), [
                'name' => 'Renamed',
                'slug' => 'archived-session-two',
                'description' => null,
                'starts_at' => $startsAt->toIso8601String(),
                'duration_minutes' => 60,
                'capacity' => 10,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('workshops', [
            'id' => $workshop->id,
            'name' => 'Archived session',
        ]);
    }
}
