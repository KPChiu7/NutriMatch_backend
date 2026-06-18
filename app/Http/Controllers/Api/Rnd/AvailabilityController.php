<?php

namespace App\Http\Controllers\Api\Rnd;

use App\Http\Controllers\Controller;
use App\Models\RndAvailabilitySchedule;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RND weekly availability schedule management.
 *
 * Business rules:
 *  - RND sets their available days and hours
 *  - day_of_week: 0=Sunday, 1=Monday ... 6=Saturday
 *  - effective_from defaults to today
 *  - effective_to is nullable (no end date = ongoing)
 *  - Multiple slots allowed per day (e.g. morning + afternoon)
 *  - is_available=false marks a day as blocked (override)
 */
class AvailabilityController extends Controller
{
    /**
     * Get the authenticated RND's full availability schedule.
     * Returns all slots (active and inactive) for schedule management.
     */
    public function index(Request $request): JsonResponse
    {
        $schedules = RndAvailabilitySchedule::where('rnd_id', $request->user()->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        // Group by day_of_week for easier frontend rendering
        $grouped = $schedules->groupBy('day_of_week')->map(fn($slots) => [
            'day_of_week' => $slots->first()->day_of_week,
            'day_name'    => $this->dayName($slots->first()->day_of_week),
            'slots'       => $slots->values(),
        ])->values();

        return response()->json([
            'rnd_id'    => $request->user()->id,
            'schedule'  => $grouped,
        ]);
    }

    /**
     * Get only currently active availability slots.
     * Used by the client booking page to show available times.
     */
    public function active(int $rndId): JsonResponse
    {
        $schedules = RndAvailabilitySchedule::where('rnd_id', $rndId)
            ->active() // scope: is_available=true AND within effective dates
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get(['id', 'day_of_week', 'start_time', 'end_time']);

        $grouped = $schedules->groupBy('day_of_week')->map(fn($slots) => [
            'day_of_week' => $slots->first()->day_of_week,
            'day_name'    => $this->dayName($slots->first()->day_of_week),
            'slots'       => $slots->map(fn($s) => [
                'id'         => $s->id,
                'start_time' => $s->start_time,
                'end_time'   => $s->end_time,
            ])->values(),
        ])->values();

        // --------------------------------------------------------
        // OPTIONAL EXTERNAL API HOOK — Calendar Integration
        // If you integrate with Google Calendar or Outlook to sync
        // the RND's existing appointments as blocked slots:
        //
        // Example (Google Calendar API):
        // $bookedSlots = app(CalendarServiceInterface::class)
        //     ->getBusySlots($rndId, now(), now()->addDays(30));
        // // Then merge $bookedSlots with $grouped to show unavailable times
        // --------------------------------------------------------

        return response()->json(['availability' => $grouped]);
    }

    /**
     * Add a new availability slot.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'day_of_week'   => ['required', 'integer', 'min:0', 'max:6'],
            'start_time'    => ['required', 'date_format:H:i'],
            'end_time'      => ['required', 'date_format:H:i', 'after:start_time'],
            'is_available'  => ['nullable', 'boolean'],
            'effective_from'=> ['nullable', 'date', 'before_or_equal:effective_to'],
            'effective_to'  => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        // Prevent overlapping slots on the same day
        $overlap = RndAvailabilitySchedule::where('rnd_id', $request->user()->id)
            ->where('day_of_week', $request->day_of_week)
            ->where('is_available', true)
            ->where(fn($q) =>
                $q->whereBetween('start_time', [$request->start_time, $request->end_time])
                  ->orWhereBetween('end_time', [$request->start_time, $request->end_time])
                  ->orWhere(fn($q2) =>
                      $q2->where('start_time', '<=', $request->start_time)
                         ->where('end_time', '>=', $request->end_time)
                  )
            )
            ->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'This time slot overlaps with an existing availability slot on the same day.',
            ], 422);
        }

        $schedule = RndAvailabilitySchedule::create([
            'rnd_id'        => $request->user()->id,
            'day_of_week'   => $request->day_of_week,
            'start_time'    => $request->start_time,
            'end_time'      => $request->end_time,
            'is_available'  => $request->boolean('is_available', true),
            'effective_from'=> $request->effective_from ?? now()->toDateString(),
            'effective_to'  => $request->effective_to,
        ]);

        // --------------------------------------------------------
        // OPTIONAL EXTERNAL API HOOK — Calendar Sync
        // Sync new availability to Google Calendar or Outlook:
        //
        // Example:
        // app(CalendarServiceInterface::class)->addAvailabilityBlock(
        //     $request->user()->id,
        //     $schedule
        // );
        // --------------------------------------------------------

        AuditService::log(
            'availability.created',
            "RND #{$request->user()->id} added availability: " .
            $this->dayName($request->day_of_week) . " {$request->start_time}–{$request->end_time}."
        );

        return response()->json([
            'message'  => 'Availability slot added.',
            'schedule' => $schedule,
        ], 201);
    }

    /**
     * Update an existing availability slot.
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'start_time'    => ['nullable', 'date_format:H:i'],
            'end_time'      => ['nullable', 'date_format:H:i', 'after:start_time'],
            'is_available'  => ['nullable', 'boolean'],
            'effective_from'=> ['nullable', 'date'],
            'effective_to'  => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $schedule = RndAvailabilitySchedule::where('rnd_id', $request->user()->id)
            ->findOrFail($id);

        $schedule->update($request->only([
            'start_time', 'end_time', 'is_available', 'effective_from', 'effective_to',
        ]));

        AuditService::log('availability.updated', "RND #{$request->user()->id} updated availability slot #{$id}.");

        return response()->json([
            'message'  => 'Availability slot updated.',
            'schedule' => $schedule->fresh(),
        ]);
    }

    /**
     * Delete an availability slot.
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $schedule = RndAvailabilitySchedule::where('rnd_id', $request->user()->id)
            ->findOrFail($id);

        $schedule->delete();

        AuditService::log('availability.deleted', "RND #{$request->user()->id} deleted availability slot #{$id}.");

        return response()->json(['message' => 'Availability slot removed.']);
    }

    /**
     * Set a day as fully unavailable (block a day off).
     * Useful for holidays, leaves, or emergencies.
     */
    public function blockDay(Request $request): JsonResponse
    {
        $request->validate([
            'day_of_week'   => ['required', 'integer', 'min:0', 'max:6'],
            'effective_from'=> ['required', 'date'],
            'effective_to'  => ['nullable', 'date', 'after_or_equal:effective_from'],
            'reason'        => ['nullable', 'string', 'max:255'],
        ]);

        // Mark all slots for this day as unavailable
        RndAvailabilitySchedule::where('rnd_id', $request->user()->id)
            ->where('day_of_week', $request->day_of_week)
            ->update(['is_available' => false]);

        // --------------------------------------------------------
        // OPTIONAL EXTERNAL API HOOK — Calendar Block
        // Block the day in the RND's synced external calendar:
        //
        // app(CalendarServiceInterface::class)->blockDay(
        //     $request->user()->id,
        //     $request->day_of_week,
        //     $request->effective_from,
        //     $request->effective_to,
        //     $request->reason ?? 'Unavailable'
        // );
        // --------------------------------------------------------

        AuditService::log(
            'availability.day_blocked',
            "RND #{$request->user()->id} blocked " . $this->dayName($request->day_of_week) . "."
        );

        return response()->json([
            'message' => $this->dayName($request->day_of_week) . ' has been blocked.',
        ]);
    }

    // --------------------------------------------------------
    // Private helpers
    // --------------------------------------------------------

    private function dayName(int $dayOfWeek): string
    {
        return match ($dayOfWeek) {
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            default => 'Unknown',
        };
    }
}
