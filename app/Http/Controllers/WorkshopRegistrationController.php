<?php

namespace App\Http\Controllers;

use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkshopRegistrationController extends Controller
{
    public function store(Request $request, Workshop $workshop): RedirectResponse
    {
        $this->authorize('register', $workshop);

        DB::transaction(function () use ($request, $workshop): void {
            $locked = Workshop::query()->whereKey($workshop->id)->lockForUpdate()->firstOrFail();

            $activeCount = WorkshopRegistration::query()
                ->where('workshop_id', $locked->id)
                ->whereNull('cancelled_at')
                ->lockForUpdate()
                ->count();

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

            if ($activeCount >= $locked->capacity) {
                throw ValidationException::withMessages([
                    'workshop' => 'This workshop is full.',
                ]);
            }

            if ($registration !== null) {
                $registration->cancelled_at = null;
                $registration->save();
            } else {
                WorkshopRegistration::query()->create([
                    'workshop_id' => $locked->id,
                    'user_id' => $request->user()->id,
                ]);
            }
        });

        $workshop->broadcastRegistrationSnapshot();

        return redirect()->route('workshops.show', $workshop);
    }

    public function destroy(Request $request, Workshop $workshop): RedirectResponse
    {
        $this->authorize('cancelRegistration', $workshop);

        $registration = WorkshopRegistration::query()
            ->where('workshop_id', $workshop->id)
            ->where('user_id', $request->user()->id)
            ->whereNull('cancelled_at')
            ->first();

        if ($registration === null) {
            throw ValidationException::withMessages([
                'workshop' => 'You are not registered for this workshop.',
            ]);
        }

        $registration->cancelled_at = now();
        $registration->save();

        $workshop->broadcastRegistrationSnapshot();

        return redirect()->route('workshops.show', $workshop);
    }
}
