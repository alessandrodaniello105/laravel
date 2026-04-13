<?php

namespace Tests\Feature;

use App\Events\PromotedFromWorkshopWaitlist;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Models\WorkshopWaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class WorkshopWaitlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_join_waitlist(): void
    {
        $workshop = Workshop::factory()->upcoming()->create(['capacity' => 1]);
        WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => User::factory(),
            'cancelled_at' => null,
        ]);

        $this->post(route('workshops.waitlist.store', $workshop))
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_user_cannot_join_waitlist_when_workshop_is_not_full(): void
    {
        $workshop = Workshop::factory()->upcoming()->create(['capacity' => 3]);
        WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => User::factory(),
            'cancelled_at' => null,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('workshops.waitlist.store', $workshop))
            ->assertForbidden();

        $this->assertDatabaseMissing('workshop_waitlist_entries', [
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_join_waitlist_when_workshop_is_full(): void
    {
        $workshop = Workshop::factory()->upcoming()->create(['capacity' => 1]);
        WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => User::factory(),
            'cancelled_at' => null,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('workshops.waitlist.store', $workshop))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workshops.show', $workshop, absolute: false));

        $this->assertDatabaseHas('workshop_waitlist_entries', [
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_cannot_join_waitlist_when_it_overlaps_another_registration(): void
    {
        $user = User::factory()->create();

        $other = Workshop::factory()->create([
            'starts_at' => now()->addDays(5)->setTime(20, 30, 0),
            'duration_minutes' => 120,
            'capacity' => 10,
        ]);

        $full = Workshop::factory()->upcoming()->create([
            'starts_at' => now()->addDays(5)->setTime(21, 30, 0),
            'duration_minutes' => 60,
            'capacity' => 1,
        ]);

        WorkshopRegistration::factory()->create([
            'workshop_id' => $other->id,
            'user_id' => $user->id,
            'cancelled_at' => null,
        ]);

        WorkshopRegistration::factory()->create([
            'workshop_id' => $full->id,
            'user_id' => User::factory(),
            'cancelled_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('workshops.waitlist.store', $full))
            ->assertSessionHasErrors('workshop');
    }

    public function test_user_can_leave_waitlist(): void
    {
        $workshop = Workshop::factory()->upcoming()->create(['capacity' => 1]);
        WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => User::factory(),
            'cancelled_at' => null,
        ]);

        $user = User::factory()->create();

        WorkshopWaitlistEntry::query()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete(route('workshops.waitlist.destroy', $workshop))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workshops.show', $workshop, absolute: false));

        $this->assertDatabaseMissing('workshop_waitlist_entries', [
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_first_waitlisted_user_is_registered_when_someone_cancels(): void
    {
        Event::fake([PromotedFromWorkshopWaitlist::class]);

        $workshop = Workshop::factory()->upcoming()->create(['capacity' => 1]);

        $holder = User::factory()->create();
        $waiter = User::factory()->create();

        WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $holder->id,
            'cancelled_at' => null,
        ]);

        WorkshopWaitlistEntry::query()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $waiter->id,
        ]);

        $this->actingAs($holder)
            ->delete(route('workshops.unregister', $workshop))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('workshop_waitlist_entries', [
            'workshop_id' => $workshop->id,
            'user_id' => $waiter->id,
        ]);

        $this->assertDatabaseHas('workshop_registrations', [
            'workshop_id' => $workshop->id,
            'user_id' => $waiter->id,
            'cancelled_at' => null,
        ]);

        Event::assertDispatched(PromotedFromWorkshopWaitlist::class, function (PromotedFromWorkshopWaitlist $event) use ($waiter, $workshop): bool {
            return $event->user->is($waiter) && $event->workshop->is($workshop);
        });
    }

    public function test_fifo_promotion_skips_waitlisted_user_who_gained_overlapping_registration(): void
    {
        $workshop = Workshop::factory()->upcoming()->create([
            'starts_at' => now()->addDays(5)->setTime(20, 0, 0),
            'duration_minutes' => 120,
            'capacity' => 2,
        ]);

        $blocking = Workshop::factory()->create([
            'starts_at' => now()->addDays(5)->setTime(21, 0, 0),
            'duration_minutes' => 60,
            'capacity' => 10,
        ]);

        $holderA = User::factory()->create();
        $holderB = User::factory()->create();
        $firstWaiter = User::factory()->create();
        $secondWaiter = User::factory()->create();

        WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $holderA->id,
            'cancelled_at' => null,
        ]);
        WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $holderB->id,
            'cancelled_at' => null,
        ]);

        WorkshopWaitlistEntry::query()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $firstWaiter->id,
            'created_at' => now()->subMinutes(2),
        ]);

        WorkshopRegistration::factory()->create([
            'workshop_id' => $blocking->id,
            'user_id' => $firstWaiter->id,
            'cancelled_at' => null,
        ]);

        WorkshopWaitlistEntry::query()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $secondWaiter->id,
            'created_at' => now()->subMinute(),
        ]);

        $this->actingAs($holderA)
            ->delete(route('workshops.unregister', $workshop))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('workshop_waitlist_entries', [
            'workshop_id' => $workshop->id,
            'user_id' => $firstWaiter->id,
        ]);

        $this->assertDatabaseMissing('workshop_waitlist_entries', [
            'workshop_id' => $workshop->id,
            'user_id' => $secondWaiter->id,
        ]);

        $this->assertDatabaseHas('workshop_registrations', [
            'workshop_id' => $workshop->id,
            'user_id' => $secondWaiter->id,
            'cancelled_at' => null,
        ]);

        $this->assertDatabaseMissing('workshop_registrations', [
            'workshop_id' => $workshop->id,
            'user_id' => $firstWaiter->id,
            'cancelled_at' => null,
        ]);
    }

    public function test_promotion_reactivates_existing_cancelled_registration_row(): void
    {
        $workshop = Workshop::factory()->upcoming()->create(['capacity' => 2]);

        $holderOne = User::factory()->create();
        $holderTwo = User::factory()->create();
        $waiter = User::factory()->create();

        WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $holderOne->id,
            'cancelled_at' => null,
        ]);

        $reactivateRow = WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $waiter->id,
            'cancelled_at' => now(),
        ]);

        WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $holderTwo->id,
            'cancelled_at' => null,
        ]);

        WorkshopWaitlistEntry::query()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $waiter->id,
        ]);

        $this->actingAs($holderTwo)
            ->delete(route('workshops.unregister', $workshop))
            ->assertSessionHasNoErrors();

        $reactivateRow->refresh();
        $this->assertNull($reactivateRow->cancelled_at);

        $this->assertSame(1, WorkshopRegistration::query()
            ->where('workshop_id', $workshop->id)
            ->where('user_id', $waiter->id)
            ->count());
    }

    public function test_workshop_show_includes_waitlist_state_for_authenticated_user(): void
    {
        $workshop = Workshop::factory()->upcoming()->create(['capacity' => 1]);
        WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => User::factory(),
            'cancelled_at' => null,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('workshops.show', $workshop))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Workshops/Show')
                ->where('isRegistered', false)
                ->where('isOnWaitlist', false)
                ->where('canJoinWaitlist', true)
                ->where('waitlistPosition', null));
    }
}
