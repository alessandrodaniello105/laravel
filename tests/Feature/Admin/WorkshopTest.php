<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertOk();
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
                'starts_at' => $startsAt->toDateTimeString(),
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
                'starts_at' => $startsAt->toDateTimeString(),
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
