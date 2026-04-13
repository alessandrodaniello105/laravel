<?php

namespace Tests\Unit;

use App\Models\Workshop;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkshopTimeOverlapTest extends TestCase
{
    #[Test]
    public function overlapping_mid_interval_returns_true(): void
    {
        $a = Workshop::factory()->make([
            'starts_at' => Carbon::parse('2026-04-16 20:30:00'),
            'duration_minutes' => 120,
        ]);
        $b = Workshop::factory()->make([
            'starts_at' => Carbon::parse('2026-04-16 21:30:00'),
            'duration_minutes' => 60,
        ]);

        $this->assertTrue($a->timeRangeOverlaps($b));
        $this->assertTrue($b->timeRangeOverlaps($a));
    }

    #[Test]
    public function back_to_back_sessions_do_not_overlap(): void
    {
        $a = Workshop::factory()->make([
            'starts_at' => Carbon::parse('2026-04-16 20:30:00'),
            'duration_minutes' => 120,
        ]);
        $b = Workshop::factory()->make([
            'starts_at' => Carbon::parse('2026-04-16 22:30:00'),
            'duration_minutes' => 60,
        ]);

        $this->assertFalse($a->timeRangeOverlaps($b));
    }
}
