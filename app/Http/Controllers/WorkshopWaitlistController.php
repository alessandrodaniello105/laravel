<?php

namespace App\Http\Controllers;

use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Models\WorkshopWaitlistEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkshopWaitlistController extends Controller
{
    public function store(Request $request, Workshop $workshop): RedirectResponse
    {
        $this->authorize('joinWaitlist', $workshop);

        DB::transaction(function () use ($request, $workshop): void {
            $locked = Workshop::query()->whereKey($workshop->id)->lockForUpdate()->firstOrFail();

            $activeCount = WorkshopRegistration::query()
                ->where('workshop_id', $locked->id)
                ->whereNull('cancelled_at')
                ->lockForUpdate()
                ->count();

            if ($activeCount < $locked->capacity) {
                throw ValidationException::withMessages([
                    'workshop' => 'This workshop is not full — register for a spot instead.',
                ]);
            }

            $registration = WorkshopRegistration::query()
                ->where('workshop_id', $locked->id)
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if ($registration !== null && $registration->cancelled_at === null) {
                throw ValidationException::withMessages([
                    'workshop' => 'You are already registered for this workshop.',
                ]);
            }

            $alreadyWaiting = WorkshopWaitlistEntry::query()
                ->where('workshop_id', $locked->id)
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->exists();

            if ($alreadyWaiting) {
                throw ValidationException::withMessages([
                    'workshop' => 'You are already on the waiting list for this workshop.',
                ]);
            }

            if ($locked->userWouldOverlapActiveRegistrations($request->user())) {
                throw ValidationException::withMessages([
                    'workshop' => 'This workshop overlaps another session you are already signed up for.',
                ]);
            }

            WorkshopWaitlistEntry::query()->create([
                'workshop_id' => $locked->id,
                'user_id' => $request->user()->id,
            ]);
        });

        return redirect()->route('workshops.show', $workshop);
    }

    public function destroy(Request $request, Workshop $workshop): RedirectResponse
    {
        $this->authorize('leaveWaitlist', $workshop);

        WorkshopWaitlistEntry::query()
            ->where('workshop_id', $workshop->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return redirect()->route('workshops.show', $workshop);
    }
}
