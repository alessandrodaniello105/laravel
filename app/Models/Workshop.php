<?php

namespace App\Models;

use App\Events\PromotedFromWorkshopWaitlist;
use App\Events\WorkshopRegistrationUpdated;
use Database\Factories\WorkshopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Workshop extends Model
{
    /** @use HasFactory<WorkshopFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'starts_at',
        'duration_minutes',
        'capacity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<WorkshopRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(WorkshopRegistration::class);
    }

    /**
     * @return HasMany<WorkshopRegistration, $this>
     */
    public function activeRegistrations(): HasMany
    {
        return $this->registrations()->whereNull('cancelled_at');
    }

    /**
     * @return HasMany<WorkshopWaitlistEntry, $this>
     */
    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WorkshopWaitlistEntry::class);
    }

    /**
     * Whether the user already has an active registration for another workshop that overlaps this schedule.
     */
    public function userWouldOverlapActiveRegistrations(User $user): bool
    {
        $otherActiveRegistrations = WorkshopRegistration::query()
            ->where('user_id', $user->id)
            ->where('workshop_id', '!=', $this->id)
            ->whereNull('cancelled_at')
            ->with('workshop')
            ->get();

        foreach ($otherActiveRegistrations as $otherRegistration) {
            $otherWorkshop = $otherRegistration->workshop;
            if ($otherWorkshop !== null && $this->timeRangeOverlaps($otherWorkshop)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Promote FIFO waitlist entries into active registrations while capacity allows.
     * Entries skipped due to schedule overlap are removed from the waitlist.
     *
     * @return int|null The last promoted user's id, if any promotions occurred in this run.
     */
    public function promoteWaitlistEntries(): ?int
    {
        $lastPromotedUserId = null;

        while ($this->activeRegistrations()->count() < $this->capacity) {
            $entry = WorkshopWaitlistEntry::query()
                ->where('workshop_id', $this->id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($entry === null) {
                break;
            }

            $user = $entry->user;

            if ($this->userWouldOverlapActiveRegistrations($user)) {
                $entry->delete();

                continue;
            }

            $existingRegistration = WorkshopRegistration::query()
                ->where('workshop_id', $this->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($existingRegistration !== null) {
                if ($existingRegistration->cancelled_at === null) {
                    $entry->delete();

                    continue;
                }

                $existingRegistration->cancelled_at = null;
                $existingRegistration->save();
            } else {
                WorkshopRegistration::query()->create([
                    'workshop_id' => $this->id,
                    'user_id' => $user->id,
                ]);
            }

            $entry->delete();
            $lastPromotedUserId = $user->id;
            PromotedFromWorkshopWaitlist::dispatch($user, $this);
        }

        return $lastPromotedUserId;
    }

    /**
     * Exclusive end instant: sessions are treated as [starts_at, endsAt).
     */
    public function endsAt(): Carbon
    {
        return $this->starts_at->copy()->addMinutes((int) $this->duration_minutes);
    }

    /**
     * Whether this workshop's scheduled interval overlaps another's (shared wall time).
     */
    public function timeRangeOverlaps(self $other): bool
    {
        return $this->starts_at->lt($other->endsAt())
            && $other->starts_at->lt($this->endsAt());
    }

    public function broadcastRegistrationSnapshot(?int $promotedUserId = null): void
    {
        $activeRegistrationsCount = $this->activeRegistrations()->count();
        $remainingSpots = max(0, $this->capacity - $activeRegistrationsCount);

        WorkshopRegistrationUpdated::dispatch(
            $this->id,
            $activeRegistrationsCount,
            $remainingSpots,
            $this->capacity,
            $promotedUserId,
        );
    }

    public static function uniqueSlugFromName(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;
        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    protected static function booted(): void
    {
        static::creating(function (Workshop $workshop): void {
            if ($workshop->slug === null || $workshop->slug === '') {
                $workshop->slug = static::uniqueSlugFromName($workshop->name);
            }
        });
    }
}
