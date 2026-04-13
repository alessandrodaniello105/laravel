<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_register_for_workshop(): void
    {
        $workshop = Workshop::factory()->upcoming()->create();

        $this->post(route('workshops.register', $workshop))
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_user_can_register_and_cancel_for_upcoming_workshop(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $workshop = Workshop::factory()->upcoming()->create([
            'capacity' => 5,
        ]);

        $this->actingAs($user)
            ->post(route('workshops.register', $workshop))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workshops.show', $workshop, absolute: false));

        $this->assertDatabaseHas('workshop_registrations', [
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
            'cancelled_at' => null,
        ]);

        $this->actingAs($user)
            ->delete(route('workshops.unregister', $workshop))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workshops.show', $workshop, absolute: false));

        $this->assertDatabaseHas('workshop_registrations', [
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
        ]);

        $registration = WorkshopRegistration::query()
            ->where('workshop_id', $workshop->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($registration);
        $this->assertNotNull($registration->cancelled_at);
    }

    public function test_user_cannot_register_twice_when_already_active(): void
    {
        $user = User::factory()->create();
        $workshop = Workshop::factory()->upcoming()->create(['capacity' => 5]);

        WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
            'cancelled_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('workshops.register', $workshop))
            ->assertSessionHasErrors('workshop');
    }

    public function test_user_cannot_register_when_workshop_is_full(): void
    {
        $user = User::factory()->create();
        $workshop = Workshop::factory()->upcoming()->create(['capacity' => 1]);

        WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => User::factory(),
            'cancelled_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('workshops.register', $workshop))
            ->assertSessionHasErrors('workshop');
    }

    public function test_user_can_re_register_after_cancelling(): void
    {
        $user = User::factory()->create();
        $workshop = Workshop::factory()->upcoming()->create(['capacity' => 2]);

        WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
            'cancelled_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('workshops.register', $workshop))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('workshop_registrations', [
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
            'cancelled_at' => null,
        ]);
    }

    public function test_user_cannot_register_for_past_workshop(): void
    {
        $user = User::factory()->create();
        $workshop = Workshop::factory()->past()->create();

        $this->actingAs($user)
            ->post(route('workshops.register', $workshop))
            ->assertForbidden();
    }

    public function test_public_workshop_pages_are_visible_to_guests(): void
    {
        $workshop = Workshop::factory()->upcoming()->create();

        $this->get(route('workshops.index'))->assertOk();
        $this->get(route('workshops.show', $workshop))->assertOk();
    }
}
