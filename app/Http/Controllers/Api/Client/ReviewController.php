<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Review;
use App\Models\RndClientRelationship;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Review and rating system for RND consultations.
 *
 * Business rules:
 *  - Only clients can submit reviews
 *  - One review per completed appointment (UNIQUE constraint on appointment_id)
 *  - Only completed appointments can be reviewed
 *  - Rating must be 1-5 stars
 *  - Client can update their own review
 *  - Reviews are publicly visible on RND profiles
 */
class ReviewController extends Controller
{
    /**
     * Submit a review for a completed appointment.
     */
    public function store(int $appointmentId, Request $request): JsonResponse
    {
        $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        // Ensure appointment belongs to this client and is completed
        $appointment = Appointment::whereHas('relationship', fn($q) =>
                $q->where('client_id', $request->user()->id)
            )
            ->where('status', 'completed')
            ->findOrFail($appointmentId);

        // Enforce one review per appointment
        if ($appointment->review) {
            return response()->json([
                'message' => 'You have already submitted a review for this appointment.',
                'review'  => $appointment->review,
            ], 422);
        }

        $review = Review::create([
            'relationship_id' => $appointment->relationship_id,
            'appointment_id'  => $appointment->id,
            'rating'          => $request->rating,
            'comment'         => $request->comment,
        ]);

        // --------------------------------------------------------
        // OPTIONAL EXTERNAL API HOOK — Email Notification to RND
        // Notify the RND that a new review has been submitted.
        //
        // Example (Laravel Mail):
        // $rnd = $appointment->relationship->rnd;
        // \Mail::to($rnd->email)->queue(
        //     new \App\Mail\NewReviewReceived($rnd, $review)
        // );
        //
        // Example (SMS via Semaphore/Vonage):
        // app(SmsServiceInterface::class)->send(
        //     $rnd->phone,
        //     "You received a {$request->rating}-star review from a client."
        // );
        // --------------------------------------------------------

        AuditService::log(
            'review.submitted',
            "Client #{$request->user()->id} submitted {$request->rating}-star review for appointment #{$appointmentId}."
        );

        return response()->json([
            'message' => 'Review submitted. Thank you for your feedback!',
            'review'  => $review,
        ], 201);
    }

    /**
     * Update an existing review.
     * Client may only update their own review.
     */
    public function update(int $reviewId, Request $request): JsonResponse
    {
        $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $review = Review::whereHas('relationship', fn($q) =>
                $q->where('client_id', $request->user()->id)
            )
            ->findOrFail($reviewId);

        $review->update([
            'rating'  => $request->rating,
            'comment' => $request->comment,
        ]);

        AuditService::log('review.updated', "Review #{$reviewId} updated by client #{$request->user()->id}.");

        return response()->json([
            'message' => 'Review updated.',
            'review'  => $review->fresh(),
        ]);
    }

    /**
     * Delete a review.
     * Client may only delete their own review.
     */
    public function destroy(int $reviewId, Request $request): JsonResponse
    {
        $review = Review::whereHas('relationship', fn($q) =>
                $q->where('client_id', $request->user()->id)
            )
            ->findOrFail($reviewId);

        $review->delete();

        AuditService::log('review.deleted', "Review #{$reviewId} deleted by client #{$request->user()->id}.");

        return response()->json(['message' => 'Review deleted.']);
    }

    /**
     * Get all reviews for a specific RND.
     * Publicly viewable — shown on RND profile page.
     */
    public function rndReviews(int $rndId, Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        // Ensure RND exists and is verified
        $rnd = User::where('role', 'rnd')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->findOrFail($rndId);

        $reviews = Review::whereHas('relationship', fn($q) =>
                $q->where('rnd_id', $rndId)
            )
            ->with([
                'appointment:id,scheduled_at,type',
                'relationship.client:id,first_name,last_name,profile_photo',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 10);

        // Compute aggregate rating stats
        $stats = Review::whereHas('relationship', fn($q) =>
                $q->where('rnd_id', $rndId)
            )
            ->select(
                \DB::raw('COUNT(*) as total'),
                \DB::raw('AVG(rating) as average'),
                \DB::raw('SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star'),
                \DB::raw('SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star'),
                \DB::raw('SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star'),
                \DB::raw('SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star'),
                \DB::raw('SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star'),
            )
            ->first();

        return response()->json([
            'rnd' => [
                'id'        => $rnd->id,
                'full_name' => $rnd->full_name,
            ],
            'stats' => [
                'total'     => (int) $stats->total,
                'average'   => $stats->average ? round($stats->average, 1) : null,
                'five_star' => (int) $stats->five_star,
                'four_star' => (int) $stats->four_star,
                'three_star'=> (int) $stats->three_star,
                'two_star'  => (int) $stats->two_star,
                'one_star'  => (int) $stats->one_star,
            ],
            'reviews' => $reviews,
        ]);
    }

    /**
     * Get the authenticated client's own review for a specific appointment.
     */
    public function myReview(int $appointmentId, Request $request): JsonResponse
    {
        $appointment = Appointment::whereHas('relationship', fn($q) =>
                $q->where('client_id', $request->user()->id)
            )
            ->findOrFail($appointmentId);

        if (! $appointment->review) {
            return response()->json([
                'message' => 'No review submitted for this appointment yet.',
                'review'  => null,
            ]);
        }

        return response()->json(['review' => $appointment->review]);
    }
}
