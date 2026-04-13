<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            User::factory()->create([
                'name' => "Participant {$n}",
                'email' => "participant{$n}@seed.internal",
                'role' => UserRole::User,
            ]);
        }
    }
}
