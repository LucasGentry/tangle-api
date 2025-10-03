<?php
/**
 * @OA\Info(
 *     title="Review API",
 *     version="1.0.0"
 * )
 */

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\CollaborationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/collaborations/{collaborationRequest}/reviews",
     *     summary="Create a new review for a collaboration",
     *     tags={"Reviews"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="collaborationRequest",
     *         in="path",
     *         required=true,
     *         description="ID of the collaboration request",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"rating"},
     *             @OA\Property(property="rating", type="integer", example=4, minimum=1, maximum=5),
     *             @OA\Property(property="comment", type="string", example="I like the collaboration", maxLength=1000)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Review created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="review", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User not authorized or review already exists"
     *     )
     * )
     */
    public function store(Request $request, CollaborationRequest $collaborationRequest)
    {
        $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        // Check if collaboration is complete
        if ($collaborationRequest->status !== 'Completed') {
            return response()->json([
                'message' => 'Cannot review an incomplete collaboration'
            ], 403);
        }

        // Check if user is part of the collaboration
        if (Auth::id() === $collaborationRequest->user_id) {
            return response()->json([
                'message' => 'You are not authorized to review this collaboration'
            ], 403);
        }

        // Determine reviewee
        $revieweeId = Auth::id() === $collaborationRequest->user_id 
            ? $collaborationRequest->requester_id 
            : $collaborationRequest->user_id;

        // Check if review already exists
        $existingReview = Review::where([
            'collaboration_request_id' => $collaborationRequest->id,
            'reviewer_id' => Auth::id(),
        ])->first();

        if ($existingReview) {
            return response()->json([
                'message' => 'You have already reviewed this collaboration'
            ], 403);
        }

        $review = Review::create([
            'collaboration_request_id' => $collaborationRequest->id,
            'reviewer_id' => Auth::id(),
            'reviewee_id' => $revieweeId,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return response()->json([
            'message' => 'Review submitted successfully',
            'review' => $review->load(['reviewer', 'reviewee'])
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/reviews/{review}/flag",
     *     summary="Flag a review for moderation",
     *     tags={"Reviews"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="review",
     *         in="path",
     *         required=true,
     *         description="ID of the review",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"flag_reason"},
     *             @OA\Property(property="flag_reason", type="string", maxLength=1000)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Review flagged successfully"
     *     )
     * )
     */
    public function flag(Request $request, Review $review)
    {
        $request->validate([
            'flag_reason' => ['required', 'string', 'max:1000'],
        ]);

        $review->update([
            'is_flagged' => true,
            'flag_reason' => $request->flag_reason,
        ]);

        return response()->json([
            'message' => 'Review has been flagged for review',
            'review' => $review
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/reviews/{review}",
     *     summary="Admin review of a flagged review",
     *     tags={"Reviews"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="review",
     *         in="path",
     *         required=true,
     *         description="ID of the review",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"is_hidden"},
     *             @OA\Property(property="is_hidden", type="boolean"),
     *             @OA\Property(property="admin_notes", type="string", maxLength=1000)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Review moderated successfully"
     *     )
     * )
     */
    public function adminReview(Request $request, Review $review)
    {
        $request->validate([
            'is_hidden' => ['required', 'boolean'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $review->update([
            'is_hidden' => $request->is_hidden,
            'admin_notes' => $request->admin_notes,
            'admin_reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Review has been moderated',
            'review' => $review
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/users/{userId}/reviews",
     *     summary="Get reviews for a specific user",
     *     tags={"Reviews"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="ID of the user",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of user reviews"
     *     )
     * )
     */
    public function userReviews(Request $request, $userId)
    {
        $reviews = Review::with(['reviewer', 'collaborationRequest'])
            ->where('reviewee_id', $userId)
            ->visible()
            ->latest()
            ->paginate(10);

        return response()->json($reviews);
    }

    /**
     * @OA\Get(
     *     path="/api/reviews/flagged",
     *     summary="Get all flagged reviews pending admin review",
     *     tags={"Reviews"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of flagged reviews"
     *     )
     * )
     */
    public function flaggedReviews()
    {
        $reviews = Review::with(['reviewer', 'reviewee', 'collaborationRequest'])
            ->flagged()
            ->whereNull('admin_reviewed_at')
            ->latest()
            ->paginate(10);

        return response()->json($reviews);
    }
} 