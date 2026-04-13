<?php

namespace App\Models;

use App\Events\WorkshopRegistrationUpdated;
use Database\Factories\WorkshopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function broadcastRegistrationSnapshot(): void
    {
        $activeRegistrationsCount = $this->activeRegistrations()->count();
        $remainingSpots = max(0, $this->capacity - $activeRegistrationsCount);

        WorkshopRegistrationUpdated::dispatch(
            $this->id,
            $activeRegistrationsCount,
            $remainingSpots,
            $this->capacity,
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
