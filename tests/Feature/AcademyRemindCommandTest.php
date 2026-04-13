<?php

namespace Tests\Feature;

use App\Mail\WorkshopTomorrowReminder;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AcademyRemindCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_command_sends_one_email_per_user_for_tomorrow_workshops(): void
    {
        Mail::fake();
        config(['app.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-04-13 10:00:00', 'UTC'));

        $user = User::factory()->create();
        $workshop = Workshop::factory()->create([
            'starts_at' => Carbon::parse('2026-04-14 14:00:00', 'UTC'),
            'capacity' => 10,
        ]);

        WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
            'cancelled_at' => null,
        ]);

        $this->artisan('academy:remind')->assertSuccessful();

        Mail::assertSent(WorkshopTomorrowReminder::class, function (WorkshopTomorrowReminder $mail) use ($user, $workshop): bool {
            return $mail->user->is($user)
                && $mail->workshops->count() === 1
                && $mail->workshops->first()->is($workshop);
        });
    }

    public function test_command_does_not_mail_for_workshops_not_on_calendar_tomorrow(): void
    {
        Mail::fake();
        config(['app.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-04-13 10:00:00', 'UTC'));

        $user = User::factory()->create();
        $workshop = Workshop::factory()->create([
            'starts_at' => Carbon::parse('2026-04-15 14:00:00', 'UTC'),
            'capacity' => 10,
        ]);

        WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
            'cancelled_at' => null,
        ]);

        $this->artisan('academy:remind')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_command_ignores_cancelled_registrations(): void
    {
        Mail::fake();
        config(['app.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-04-13 10:00:00', 'UTC'));

        $user = User::factory()->create();
        $workshop = Workshop::factory()->create([
            'starts_at' => Carbon::parse('2026-04-14 14:00:00', 'UTC'),
            'capacity' => 10,
        ]);

        WorkshopRegistration::factory()->cancelled()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
        ]);

        $this->artisan('academy:remind')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_dry_run_does_not_send_mail(): void
    {
        Mail::fake();
        config(['app.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-04-13 10:00:00', 'UTC'));

        $user = User::factory()->create();
        $workshop = Workshop::factory()->create([
            'starts_at' => Carbon::parse('2026-04-14 14:00:00', 'UTC'),
            'capacity' => 10,
        ]);

        WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
            'cancelled_at' => null,
        ]);

        $this->artisan('academy:remind', ['--dry-run' => true])->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_command_groups_multiple_tomorrow_workshops_for_same_user_into_one_mail(): void
    {
        Mail::fake();
        config(['app.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-04-13 10:00:00', 'UTC'));

        $user = User::factory()->create();
        $morning = Workshop::factory()->create([
            'name' => 'Morning',
            'starts_at' => Carbon::parse('2026-04-14 09:00:00', 'UTC'),
            'duration_minutes' => 60,
            'capacity' => 20,
        ]);
        $afternoon = Workshop::factory()->create([
            'name' => 'Afternoon',
            'starts_at' => Carbon::parse('2026-04-14 15:00:00', 'UTC'),
            'duration_minutes' => 60,
            'capacity' => 20,
        ]);

        WorkshopRegistration::factory()->create([
            'workshop_id' => $morning->id,
            'user_id' => $user->id,
            'cancelled_at' => null,
        ]);
        WorkshopRegistration::factory()->create([
            'workshop_id' => $afternoon->id,
            'user_id' => $user->id,
            'cancelled_at' => null,
        ]);

        $this->artisan('academy:remind')->assertSuccessful();

        Mail::assertSent(WorkshopTomorrowReminder::class, function (WorkshopTomorrowReminder $mail) use ($user, $morning, $afternoon): bool {
            if (! $mail->user->is($user) || $mail->workshops->count() !== 2) {
                return false;
            }

            $ids = $mail->workshops->pluck('id')->sort()->values()->all();

            return $ids === collect([$morning->id, $afternoon->id])->sort()->values()->all();
        });

        Mail::assertSent(WorkshopTomorrowReminder::class, 1);
    }
}
