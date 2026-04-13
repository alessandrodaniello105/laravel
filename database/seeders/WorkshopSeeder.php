<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class WorkshopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $participantIds = User::query()
            ->where('role', UserRole::User)
            ->where('email', 'like', 'participant%@seed.internal')
            ->orderBy('email')
            ->pluck('id')
            ->values();

        if ($participantIds->count() < 30) {
            $this->command?->warn('WorkshopSeeder expects 30 participant users (UserSeeder). Found '.$participantIds->count().'.');

            return;
        }

        $baseDay = Carbon::now()->addDays(21)->startOfDay();

        /** @var list<array{name: string, slug: string, description: string, starts_at: Carbon, duration_minutes: int, capacity: int}> $definitions */
        $definitions = [
            [
                'name' => 'Yoga/Pilates session',
                'slug' => 'yoga-pilates-session',
                'description' => 'Morning mobility block (overlaps the next two sessions in time).',
                'starts_at' => $baseDay->copy()->setTime(10, 0, 0),
                'duration_minutes' => 120,
                'capacity' => 10,
            ],
            [
                'name' => 'How to build social skills',
                'slug' => 'how-to-build-social-skills',
                'description' => 'Overlaps Yoga/Pilates and Malenia prep block.',
                'starts_at' => $baseDay->copy()->setTime(11, 0, 0),
                'duration_minutes' => 90,
                'capacity' => 10,
            ],
            [
                'name' => 'How to beat Malenia',
                'slug' => 'how-to-beat-malenia',
                'description' => 'Boss patterns — overlaps both morning sessions (max 3 concurrent 11:30–12:00).',
                'starts_at' => $baseDay->copy()->setTime(11, 30, 0),
                'duration_minutes' => 90,
                'capacity' => 10,
            ],
            [
                'name' => 'How to have a social life pt. 1',
                'slug' => 'how-to-have-a-social-life-pt-1',
                'description' => 'Afternoon session — no overlap with morning block; 2 seats left after seeding.',
                'starts_at' => $baseDay->copy()->setTime(14, 0, 0),
                'duration_minutes' => 60,
                'capacity' => 10,
            ],
            [
                'name' => 'How to have a social life pt. 2',
                'slug' => 'how-to-have-a-social-life-pt-2',
                'description' => 'Later afternoon — does not overlap pt. 1.',
                'starts_at' => $baseDay->copy()->setTime(16, 0, 0),
                'duration_minutes' => 120,
                'capacity' => 8,
            ],
        ];

        $workshops = collect($definitions)->map(function (array $def): Workshop {
            return Workshop::query()->create([
                'name' => $def['name'],
                'slug' => $def['slug'],
                'description' => $def['description'],
                'starts_at' => $def['starts_at'],
                'duration_minutes' => $def['duration_minutes'],
                'capacity' => $def['capacity'],
            ]);
        });

        $w1 = $workshops[0];
        $w2 = $workshops[1];
        $w3 = $workshops[2];
        $w4 = $workshops[3];
        $w5 = $workshops[4];

        $this->registerUsers($w1->id, $participantIds->slice(0, 10)->all());
        $this->registerUsers($w2->id, $participantIds->slice(10, 10)->all());
        $this->registerUsers($w3->id, $participantIds->slice(20, 10)->all());

        $this->registerUsers($w4->id, $participantIds->slice(0, 8)->all());

        $this->registerUsers($w5->id, $participantIds->slice(0, 3)->all());
    }

    /**
     * @param  list<int>  $userIds
     */
    private function registerUsers(int $workshopId, array $userIds): void
    {
        foreach ($userIds as $userId) {
            WorkshopRegistration::query()->create([
                'workshop_id' => $workshopId,
                'user_id' => $userId,
                'cancelled_at' => null,
            ]);
        }
    }
}
