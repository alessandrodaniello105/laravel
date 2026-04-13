<?php

namespace App\Console\Commands;

use App\Mail\WorkshopTomorrowReminder;
use App\Models\WorkshopRegistration;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class AcademyRemindCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'academy:remind
                            {--dry-run : List who would receive mail without sending}';

    /**
     * @var string
     */
    protected $description = 'Send reminder emails to all participants registered for workshops scheduled tomorrow (app timezone).';

    public function handle(): int
    {
        $timezone = (string) config('app.timezone');
        $tomorrowDate = now($timezone)->addDay()->toDateString();

        $registrations = WorkshopRegistration::query()
            ->active()
            ->whereHas('workshop', function ($query) use ($tomorrowDate): void {
                $query->whereDate('starts_at', $tomorrowDate);
            })
            ->with(['user', 'workshop'])
            ->orderBy('id')
            ->get();

        if ($registrations->isEmpty()) {
            $this->info("No active registrations for workshops on {$tomorrowDate} ({$timezone}).");

            return self::SUCCESS;
        }

        $byUser = $registrations->groupBy('user_id');
        $dryRun = (bool) $this->option('dry-run');

        foreach ($byUser as $userRegs) {
            /** @var Collection<int, WorkshopRegistration> $userRegs */
            $first = $userRegs->first();
            $user = $first->user;
            $workshops = $userRegs->map(fn (WorkshopRegistration $registration) => $registration->workshop)->filter();

            if ($user === null || $workshops->isEmpty()) {
                continue;
            }

            if ($dryRun) {
                $names = $workshops->pluck('name')->implode(', ');
                $this->line("[dry-run] {$user->email} — {$names}");

                continue;
            }

            Mail::to($user)->send(new WorkshopTomorrowReminder($user, $workshops));
        }

        $userCount = $byUser->count();
        $message = $dryRun
            ? "[dry-run] Would send {$userCount} reminder(s) for {$tomorrowDate}."
            : "Sent {$userCount} reminder email(s) for workshops on {$tomorrowDate}.";

        $this->info($message);

        return self::SUCCESS;
    }
}
