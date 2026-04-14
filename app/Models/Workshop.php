<?php

namespace App\Models;

use App\Events\AdminWorkshopStatisticsUpdated;
use App\Events\PromotedFromWorkshopWaitlist;
use App\Events\WorkshopRegistrationUpdated;
use Database\Factories\WorkshopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

        static::broadcastAdminWorkshopStatistics();
    }

    /**
     * All-time popularity groups rows by exact workshop title (`name`) and sums every registration
     * (including cancelled). The linked id/slug is the lowest-id workshop in the winning title group.
     *
     * @return array{
     *     most_popular_workshop: array{id: int, name: string, slug: string, active_registrations_count: int}|null,
     *     most_popular_all_time_workshop: array{id: int, name: string, slug: string, total_registrations_count: int}|null,
     *     total_active_registrations: int
     * }
     */
    public static function adminStatisticsSnapshot(): array
    {
        $rows = static::query()
            ->withCount([
                'registrations as active_registrations_count' => function ($query): void {
                    $query->whereNull('cancelled_at');
                },
                'registrations as total_registrations_count',
            ])
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return [
                'most_popular_workshop' => null,
                'most_popular_all_time_workshop' => null,
                'total_active_registrations' => 0,
            ];
        }

        $sortedActive = $rows->sortBy([
            ['active_registrations_count', 'desc'],
            ['id', 'asc'],
        ])->values();

        $topActive = $sortedActive->first();
        \assert($topActive !== null);

        /** @var Collection<string, Collection<int, Workshop>> $byTitle */
        $byTitle = $rows->groupBy('name');

        $titleAggregates = $byTitle->map(function (Collection $workshops, string $title): array {
            $representative = $workshops->sortBy('id')->first();
            \assert($representative instanceof Workshop);

            return [
                'name' => $title,
                'total_registrations_count' => (int) $workshops->sum(
                    fn (Workshop $w): int => (int) $w->total_registrations_count,
                ),
                'representative_id' => $representative->id,
                'representative_slug' => $representative->slug,
            ];
        });

        $choice = $titleAggregates
            ->values()
            ->sort(function (array $a, array $b): int {
                if ($a['total_registrations_count'] !== $b['total_registrations_count']) {
                    return $b['total_registrations_count'] <=> $a['total_registrations_count'];
                }

                return strcmp($a['name'], $b['name']);
            })
            ->first();

        $mostPopularAllTime = null;
        if ($choice !== null && $choice['total_registrations_count'] > 0) {
            $mostPopularAllTime = [
                'id' => $choice['representative_id'],
                'name' => $choice['name'],
                'slug' => $choice['representative_slug'],
                'total_registrations_count' => $choice['total_registrations_count'],
            ];
        }

        return [
            'most_popular_workshop' => [
                'id' => $topActive->id,
                'name' => $topActive->name,
                'slug' => $topActive->slug,
                'active_registrations_count' => (int) $topActive->active_registrations_count,
            ],
            'most_popular_all_time_workshop' => $mostPopularAllTime,
            'total_active_registrations' => (int) $rows->sum('active_registrations_count'),
        ];
    }

    public static function broadcastAdminWorkshopStatistics(): void
    {
        AdminWorkshopStatisticsUpdated::dispatch(static::adminStatisticsSnapshot());
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
