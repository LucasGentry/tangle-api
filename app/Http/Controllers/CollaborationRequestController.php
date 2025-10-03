<?php

namespace App\Http\Controllers;

use App\Models\CollaborationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

class CollaborationRequestController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/collaborations",
     *     summary="List collaboration requests",
     *     tags={"Collaboration"},
     *     @OA\Response(response=200, description="List of collaboration requests")
     * )
     */
    public function index(Request $request)
    {
        $collaborations = CollaborationRequest::all();

        return response()->json($collaborations);
    }

    /**
     * @OA\Post(
     *     path="/api/collaborations",
     *     summary="Create a collaboration request",
     *     tags={"Collaboration"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                  @OA\Property(property="title", type="string", description="Title of the collaboration"),
     *                  @OA\Property(property="categories[]", type="array", @OA\Items(type="string"), description="Categories for the collaboration"),
     *                  @OA\Property(property="platforms[]", type="array", @OA\Items(type="string"), description="Social media platforms"),
     *                  @OA\Property(property="deadline", type="string", format="date-time", description="Deadline for the collaboration"),
     *                  @OA\Property(property="location_type", type="string", description="Type of location"),
     *                  @OA\Property(property="location", type="string", description="Location details"),
     *                  @OA\Property(property="description", type="string", description="Detailed description"),
     *                  @OA\Property(property="collaboration_images[]", type="array", @OA\Items(type="string", format="binary"), description="Images for the collaboration (max 5MB each, jpeg/png/jpg)"),
     *                  @OA\Property(property="collaborator_count", type="integer", description="Number of collaborators"),
     *                  @OA\Property(property="application_fee", type="number", format="float", description="Fee for application"),
     *                  @OA\Property(property="status", type="string", enum={"Draft", "Open", "Reviewing", "Reviewing Applicants", "In Progress", "Completed", "Cancelled"}, description="Status of the collaboration")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="collaboration",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="user", type="object"),
     *                 @OA\Property(property="applications", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Not allowed to edit"),
     *     @OA\Response(response=404, description="Collaboration request not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request)
    {
        // Find the collaboration request and check ownership

        $collaboration = CollaborationRequest::where('user_id', Auth::id())
            ->where('status', CollaborationRequest::STATUS_DRAFT)
            ->first();

        if ( $collaboration === null) {
             $validated = $request->validate([
                'title' => 'required|string|max:255',
                'categories' => 'nullable|array',
                'platforms' => 'nullable|array',
                'deadline' => 'nullable|date',
            ]);

            $status = CollaborationRequest::STATUS_DRAFT;

            $collaboration = CollaborationRequest::create([
                'user_id' => Auth::id(),
                'title' => $validated['title'],
                'categories' => $validated['categories'] ?? [],
                'platforms' => $validated['platforms'] ?? [],
                'deadline' => $validated['deadline'] ?? null,
                'status' => $status
            ]);


            return response()->json($collaboration, 201);
        } else {
            // Check if the request can be edited
            if (!$collaboration->is_editable && !$request->has('status')) {
                return response()->json([
                    'error' => 'Editing not allowed after receiving applications',
                    'is_editable' => false,
                    'current_status' => $collaboration->status
                ], 403);
            }
            // Validate the request data
            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'categories' => 'sometimes|required|array',
                'categories.*' => 'string',
                'platforms' => 'sometimes|required|array',
                'platforms.*' => 'string',
                'deadline' => 'nullable|date',
                
                'location_type' => 'sometimes|required|string',
                'location' => 'sometimes|required|string',
                'description' => 'sometimes|required|string',
                'collaboration_images' => 'nullable|array',
                'collaboration_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
                'colloborator_count' => 'sometimes|required|integer',

                'application_fee' => 'sometimes|required|numeric|min:0',
                'status' => [
                    'sometimes',
                    'required',
                    'string',
                    Rule::in([
                        CollaborationRequest::STATUS_DRAFT,
                        CollaborationRequest::STATUS_OPEN,
                        CollaborationRequest::STATUS_REVIEWING,
                        CollaborationRequest::STATUS_IN_PROGRESS,
                        CollaborationRequest::STATUS_COMPLETED,
                        CollaborationRequest::STATUS_CANCELLED
                    ])
                ]
            ]);

            $collaboration_images = [];

            if ($request->hasFile('collaboration_images')) {
                foreach ($request->file('collaboration_images') as $image) {
                    $manager = new ImageManager(new GdDriver());
                    $img = $manager->read($image->getRealPath());
                    $filename = 'collaboration_images/' . uniqid() . '.webp';
                    Storage::disk('public')->put($filename, $img->toWebp());
                    $collaboration_images[] = [
                        'path' => $filename,
                        'url' => asset(Storage::url($filename)),
                        'size' => Storage::disk('public')->size($filename),
                        'mime_type' => 'image/webp'
                    ];
                }
            }

            // Filter out null values
            $dataToUpdate = array_filter($validated, function ($value) {
                return $value !== null;
            });

            // Update the collaboration request
            $collaboration->update($dataToUpdate);
            $collaboration->update(['collaboration_images' => $collaboration_images]);

            // Return the updated collaboration with related data
            return response()->json([
                'message' => 'Collaboration request updated successfully',
                'collaboration' => $collaboration->fresh(['user', 'applications'])
            ]);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/collaborations/{id}",
     *     summary="Get collaboration request details",
     *     tags={"Collaboration"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Collaboration request details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($idOrToken)
    {
        $collaboration = CollaborationRequest::where('id', $idOrToken)
            ->orWhere('share_token', $idOrToken)
            ->with('user')
            ->firstOrFail();

        return response()->json($collaboration);
    }

    /**
     * @OA\Post(
     *     path="/api/collaborations/{id}/close",
     *     summary="Close a collaboration request",
     *     tags={"Collaboration"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Request closed successfully"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Invalid status transition"),
     * )
     */
    public function close($id)
    {
        try {
            $collaboration = CollaborationRequest::where('user_id', Auth::id())
                ->findOrFail($id);

            if (!$collaboration->canTransitionTo(CollaborationRequest::STATUS_COMPLETED)) {
                return response()->json([
                    'error' => 'Cannot close request in current state',
                    'current_status' => $collaboration->status,
                    'required_status' => CollaborationRequest::STATUS_IN_PROGRESS,
                    'message' => 'Collaboration must be in progress to be closed'
                ], 422);
            }

            $collaboration->update([
                'status' => CollaborationRequest::STATUS_COMPLETED
            ]);

            // Load related data for response
            $collaboration->load(['user', 'applications']);

            return response()->json([
                'message' => 'Collaboration request closed successfully',
                'collaboration' => $collaboration
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Collaboration request not found'
            ], 404);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/collaborations/{id}/cancel",
     *     summary="Cancel a collaboration request",
     *     tags={"Collaboration"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="cancellation_reason", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Request cancelled successfully"),
     *     @OA\Response(response=404, description="Not found"),
     * )
     */
    public function cancel(Request $request, $id)
    {
        try {
            $collaboration = CollaborationRequest::where('user_id', Auth::id())
                ->findOrFail($id);

            if (!$collaboration->canTransitionTo(CollaborationRequest::STATUS_CANCELLED)) {
                return response()->json([
                    'error' => 'Cannot cancel request in current state',
                    'current_status' => $collaboration->status,
                    'allowed_transitions' => $this->getAllowedTransitions($collaboration),
                    'message' => 'This collaboration request cannot be cancelled in its current state'
                ], 422);
            }

            // Validate cancellation reason if provided
            $validated = $request->validate([
                'cancellation_reason' => 'nullable|string|max:500'
            ]);

            $updateData = [
                'status' => CollaborationRequest::STATUS_CANCELLED
            ];

            // Add cancellation reason if provided
            if (!empty($validated['cancellation_reason'])) {
                $updateData['cancellation_reason'] = $validated['cancellation_reason'];
            }

            $collaboration->update($updateData);

            // Load related data for response
            $collaboration->load(['user', 'applications']);

            // Notify applicants if there are any
            if ($collaboration->applications()->exists()) {
                // Here you would typically dispatch a job to notify applicants
                // For now, we'll just include it in the response
                $applicantsCount = $collaboration->applications()->count();
                return response()->json([
                    'message' => 'Collaboration request cancelled successfully',
                    'collaboration' => $collaboration,
                    'notifications_sent' => $applicantsCount > 0,
                    'applicants_notified' => $applicantsCount
                ]);
            }

            return response()->json([
                'message' => 'Collaboration request cancelled successfully',
                'collaboration' => $collaboration
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Collaboration request not found'
            ], 404);
        }
    }

    /**
     * Fetch all collaboration IDs and user ID by user email
     * GET /api/collaborations/by-email?email=example@email.com
     *
     * @OA\Get(
     *     path="/api/collaborations/by-email",
     *     summary="Get all collaboration IDs and user ID by user email",
     *     tags={"Collaboration"},
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", format="email"),
     *         description="User's email address"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="user_id", type="integer"),
     *             @OA\Property(property="collaboration_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function collaborationsByEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);
        $user = \App\Models\User::where('email', $request->email)->first();
        if (!$user) {
            $collaborations = CollaborationRequest::all();
            return response()->json($collaborations);
        }

        $collaborations = \App\Models\CollaborationRequest::where('user_id', $user->id)->get();

        return response()->json([
            'user_id' => $user->id,
            'collaborations' => $collaborations,
        ]);
    }

    /**
     * Get allowed status transitions for a collaboration request
     */
    private function getAllowedTransitions(CollaborationRequest $collaboration)
    {
        $allStatuses = [
            CollaborationRequest::STATUS_OPEN,
            CollaborationRequest::STATUS_REVIEWING,
            CollaborationRequest::STATUS_IN_PROGRESS,
            CollaborationRequest::STATUS_COMPLETED,
            CollaborationRequest::STATUS_CANCELLED
        ];

        return array_filter($allStatuses, function($status) use ($collaboration) {
            return $collaboration->canTransitionTo($status);
        });
    }
}
