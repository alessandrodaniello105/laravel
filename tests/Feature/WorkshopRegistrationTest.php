<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Events\WorkshopRegistrationUpdated;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;
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
        Event::fake([WorkshopRegistrationUpdated::class]);

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

        Event::assertDispatched(WorkshopRegistrationUpdated::class, function (WorkshopRegistrationUpdated $event) use ($workshop): bool {
            return $event->workshopId === $workshop->id
                && $event->activeRegistrationsCount === 1
                && $event->remainingSpots === 4
                && $event->capacity === 5;
        });

        $this->assertDatabaseHas('workshop_registrations', [
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
            'cancelled_at' => null,
        ]);

        $this->actingAs($user)
            ->delete(route('workshops.unregister', $workshop))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workshops.show', $workshop, absolute: false));

        Event::assertDispatched(WorkshopRegistrationUpdated::class, function (WorkshopRegistrationUpdated $event) use ($workshop): bool {
            return $event->workshopId === $workshop->id
                && $event->activeRegistrationsCount === 0
                && $event->remainingSpots === 5;
        });

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

    public function test_user_cannot_register_for_workshop_that_overlaps_another_active_registration(): void
    {
        $user = User::factory()->create();
        $first = Workshop::factory()->create([
            'starts_at' => now()->addDays(5)->setTime(20, 30, 0),
            'duration_minutes' => 120,
            'capacity' => 10,
        ]);
        $second = Workshop::factory()->create([
            'starts_at' => now()->addDays(5)->setTime(21, 30, 0),
            'duration_minutes' => 60,
            'capacity' => 10,
        ]);

        WorkshopRegistration::factory()->create([
            'workshop_id' => $first->id,
            'user_id' => $user->id,
            'cancelled_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('workshops.register', $second))
            ->assertSessionHasErrors('workshop');
    }

    public function test_user_may_register_for_workshop_that_starts_when_another_ends(): void
    {
        $user = User::factory()->create();
        $first = Workshop::factory()->create([
            'starts_at' => now()->addDays(5)->setTime(20, 30, 0),
            'duration_minutes' => 120,
            'capacity' => 10,
        ]);
        $second = Workshop::factory()->create([
            'starts_at' => now()->addDays(5)->setTime(22, 30, 0),
            'duration_minutes' => 60,
            'capacity' => 10,
        ]);

        WorkshopRegistration::factory()->create([
            'workshop_id' => $first->id,
            'user_id' => $user->id,
            'cancelled_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('workshops.register', $second))
            ->assertSessionHasNoErrors();
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

    public function test_user_cannot_cancel_registration_after_workshop_has_started(): void
    {
        $user = User::factory()->create();
        $workshop = Workshop::factory()->past()->create();

        WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
            'cancelled_at' => null,
        ]);

        $this->actingAs($user)
            ->delete(route('workshops.unregister', $workshop))
            ->assertForbidden();
    }

    public function test_workshop_show_indicates_when_registration_is_closed(): void
    {
        $past = Workshop::factory()->past()->create();
        $future = Workshop::factory()->upcoming()->create();

        $this->get(route('workshops.show', $past))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Workshops/Show')
                ->where('registrationOpen', false));

        $this->get(route('workshops.show', $future))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Workshops/Show')
                ->where('registrationOpen', true));
    }

    public function test_public_workshop_pages_are_visible_to_guests(): void
    {
        $workshop = Workshop::factory()->upcoming()->create();

        $this->get(route('workshops.index'))->assertOk();
        $this->get(route('workshops.show', $workshop))->assertOk();
    }

    public function test_workshops_index_separates_upcoming_and_past(): void
    {
        Workshop::factory()->past()->create(['name' => 'Past Session']);
        Workshop::factory()->upcoming()->create(['name' => 'Future Session']);

        $this->get(route('workshops.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Workshops/Index')
                ->has('workshops.data', 1)
                ->where('workshops.data.0.name', 'Future Session')
                ->has('pastWorkshops', 1)
                ->where('pastWorkshops.0.name', 'Past Session'));
    }
}
