<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Models\CollaborationRequest;
use App\Models\Application;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class DisputeController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * @OA\Get(
     *     path="/api/disputes",
     *     summary="Get user's disputes",
     *     tags={"Disputes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         @OA\Schema(type="string", enum={"open", "under_review", "resolved", "closed"})
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         @OA\Schema(type="string", enum={"payment", "quality", "deadline", "communication", "other"})
     *     ),
     *     @OA\Response(response=200, description="List of disputes")
     * )
     */
    public function index(Request $request)
    {
        $query = Dispute::with(['collaborationRequest', 'initiator', 'respondent'])
            ->forUser(Auth::id())
            ->when($request->status, function ($q, $status) {
                return $q->where('status', $status);
            })
            ->when($request->type, function ($q, $type) {
                return $q->where('type', $type);
            })
            ->latest();

        $disputes = $query->paginate(15);

        return response()->json($disputes);
    }

    /**
     * @OA\Post(
     *     path="/api/disputes",
     *     summary="Open a new dispute",
     *     tags={"Disputes"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"collaboration_request_id", "respondent_id", "type", "description"},
     *             @OA\Property(property="collaboration_request_id", type="integer"),
     *             @OA\Property(property="respondent_id", type="integer"),
     *             @OA\Property(property="type", type="string", enum={"payment", "quality", "deadline", "communication", "other"}),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="evidence", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=201, description="Dispute created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'collaboration_request_id' => 'required|exists:collaboration_requests,id',
            'respondent_id' => 'required|exists:users,id',
            'type' => ['required', Rule::in([
                Dispute::TYPE_PAYMENT,
                Dispute::TYPE_QUALITY,
                Dispute::TYPE_DEADLINE,
                Dispute::TYPE_COMMUNICATION,
                Dispute::TYPE_OTHER
            ])],
            'description' => 'required|string|max:2000',
            'evidence' => 'nullable|array',
            'evidence.*' => 'string|url'
        ]);

        // Check if user is involved in the collaboration
        $collaboration = CollaborationRequest::findOrFail($validated['collaboration_request_id']);
        if ($collaboration->user_id !== Auth::id() && 
            !$collaboration->applications()->where('user_id', Auth::id())->exists()) {
            return response()->json(['error' => 'You are not involved in this collaboration'], 403);
        }

        // Check if dispute already exists
        $existingDispute = Dispute::where('collaboration_request_id', $validated['collaboration_request_id'])
            ->where(function ($q) {
                $q->where('initiator_id', Auth::id())
                  ->orWhere('respondent_id', Auth::id());
            })
            ->whereIn('status', [Dispute::STATUS_OPEN, Dispute::STATUS_UNDER_REVIEW])
            ->first();

        if ($existingDispute) {
            return response()->json(['error' => 'A dispute already exists for this collaboration'], 422);
        }

        DB::beginTransaction();
        try {
            $dispute = Dispute::create([
                'collaboration_request_id' => $validated['collaboration_request_id'],
                'initiator_id' => Auth::id(),
                'respondent_id' => $validated['respondent_id'],
                'type' => $validated['type'],
                'description' => $validated['description'],
                'evidence' => $validated['evidence'] ?? []
            ]);

            // Notify respondent
            $this->notificationService->send(
                $dispute->respondent,
                'dispute_opened',
                [
                    'dispute_id' => $dispute->id,
                    'collaboration_title' => $collaboration->title,
                    'initiator_name' => Auth::user()->name,
                    'type' => $dispute->type
                ]
            );

            DB::commit();

            return response()->json([
                'message' => 'Dispute opened successfully',
                'dispute' => $dispute->load(['collaborationRequest', 'initiator', 'respondent'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create dispute', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to open dispute'], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/disputes/{id}",
     *     summary="Get dispute details",
     *     tags={"Disputes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Dispute details"),
     *     @OA\Response(response=404, description="Dispute not found")
     * )
     */
    public function show($id)
    {
        $dispute = Dispute::with(['collaborationRequest', 'initiator', 'respondent', 'resolvedBy'])
            ->forUser(Auth::id())
            ->findOrFail($id);

        return response()->json($dispute);
    }

    /**
     * @OA\Post(
     *     path="/api/disputes/{id}/respond",
     *     summary="Respond to a dispute",
     *     tags={"Disputes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"response"},
     *             @OA\Property(property="response", type="string"),
     *             @OA\Property(property="evidence", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Response submitted successfully"),
     *     @OA\Response(response=404, description="Dispute not found")
     * )
     */
    public function respond(Request $request, $id)
    {
        $dispute = Dispute::where('respondent_id', Auth::id())
            ->where('id', $id)
            ->whereIn('status', [Dispute::STATUS_OPEN, Dispute::STATUS_UNDER_REVIEW])
            ->firstOrFail();

        $validated = $request->validate([
            'response' => 'required|string|max:2000',
            'evidence' => 'nullable|array',
            'evidence.*' => 'string|url'
        ]);

        // Add response to evidence
        $evidence = $dispute->evidence ?? [];
        $evidence[] = [
            'type' => 'response',
            'content' => $validated['response'],
            'submitted_by' => Auth::id(),
            'submitted_at' => now()->toISOString()
        ];

        if (!empty($validated['evidence'])) {
            foreach ($validated['evidence'] as $evidenceItem) {
                $evidence[] = [
                    'type' => 'evidence',
                    'content' => $evidenceItem,
                    'submitted_by' => Auth::id(),
                    'submitted_at' => now()->toISOString()
                ];
            }
        }

        $dispute->update(['evidence' => $evidence]);

        // Notify initiator
        $this->notificationService->send(
            $dispute->initiator,
            'dispute_response',
            [
                'dispute_id' => $dispute->id,
                'collaboration_title' => $dispute->collaborationRequest->title,
                'respondent_name' => Auth::user()->name
            ]
        );

        return response()->json([
            'message' => 'Response submitted successfully',
            'dispute' => $dispute->fresh(['collaborationRequest', 'initiator', 'respondent'])
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/disputes/{id}/close",
     *     summary="Close a dispute (initiator only)",
     *     tags={"Disputes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Dispute closed successfully"),
     *     @OA\Response(response=404, description="Dispute not found")
     * )
     */
    public function close($id)
    {
        $dispute = Dispute::where('initiator_id', Auth::id())
            ->where('id', $id)
            ->whereIn('status', [Dispute::STATUS_OPEN, Dispute::STATUS_UNDER_REVIEW])
            ->firstOrFail();

        if ($dispute->close()) {
            // Notify respondent
            $this->notificationService->send(
                $dispute->respondent,
                'dispute_closed',
                [
                    'dispute_id' => $dispute->id,
                    'collaboration_title' => $dispute->collaborationRequest->title,
                    'initiator_name' => Auth::user()->name
                ]
            );

            return response()->json([
                'message' => 'Dispute closed successfully',
                'dispute' => $dispute->fresh(['collaborationRequest', 'initiator', 'respondent'])
            ]);
        }

        return response()->json(['error' => 'Cannot close dispute in current state'], 422);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/disputes",
     *     summary="Get all disputes (admin only)",
     *     tags={"Admin Disputes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="List of all disputes")
     * )
     */
    public function adminIndex(Request $request)
    {
        $query = Dispute::with(['collaborationRequest', 'initiator', 'respondent', 'resolvedBy'])
            ->when($request->status, function ($q, $status) {
                return $q->where('status', $status);
            })
            ->when($request->type, function ($q, $type) {
                return $q->where('type', $type);
            })
            ->when($request->auto_opened, function ($q, $autoOpened) {
                return $autoOpened ? $q->autoOpened() : $q->manuallyOpened();
            })
            ->latest();

        $disputes = $query->paginate(20);

        return response()->json($disputes);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/disputes/{id}/resolve",
     *     summary="Resolve a dispute (admin only)",
     *     tags={"Admin Disputes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"resolution", "resolution_notes"},
     *             @OA\Property(property="resolution", type="string", enum={"payout_to_requestor", "refund_to_applicants", "shared_fault", "no_action"}),
     *             @OA\Property(property="resolution_notes", type="string"),
     *             @OA\Property(property="admin_notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Dispute resolved successfully"),
     *     @OA\Response(response=404, description="Dispute not found")
     * )
     */
    public function adminResolve(Request $request, $id)
    {
        $dispute = Dispute::with(['collaborationRequest', 'initiator', 'respondent'])
            ->findOrFail($id);

        $validated = $request->validate([
            'resolution' => ['required', Rule::in([
                Dispute::RESOLUTION_PAYOUT_TO_REQUESTOR,
                Dispute::RESOLUTION_REFUND_TO_APPLICANTS,
                Dispute::RESOLUTION_SHARED_FAULT,
                Dispute::RESOLUTION_NO_ACTION
            ])],
            'resolution_notes' => 'required|string|max:2000',
            'admin_notes' => 'nullable|string|max:1000'
        ]);

        DB::beginTransaction();
        try {
            if ($dispute->resolve($validated['resolution'], $validated['resolution_notes'], Auth::id())) {
                // Update admin notes if provided
                if (!empty($validated['admin_notes'])) {
                    $dispute->update(['admin_notes' => $validated['admin_notes']]);
                }

                // Notify both parties
                $this->notificationService->send(
                    $dispute->initiator,
                    'dispute_resolved',
                    [
                        'dispute_id' => $dispute->id,
                        'collaboration_title' => $dispute->collaborationRequest->title,
                        'resolution' => $dispute->resolution,
                        'resolution_notes' => $dispute->resolution_notes
                    ]
                );

                $this->notificationService->send(
                    $dispute->respondent,
                    'dispute_resolved',
                    [
                        'dispute_id' => $dispute->id,
                        'collaboration_title' => $dispute->collaborationRequest->title,
                        'resolution' => $dispute->resolution,
                        'resolution_notes' => $dispute->resolution_notes
                    ]
                );

                DB::commit();

                return response()->json([
                    'message' => 'Dispute resolved successfully',
                    'dispute' => $dispute->fresh(['collaborationRequest', 'initiator', 'respondent', 'resolvedBy'])
                ]);
            }

            DB::rollBack();
            return response()->json(['error' => 'Cannot resolve dispute in current state'], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to resolve dispute', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to resolve dispute'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/admin/disputes/{id}/review",
     *     summary="Mark dispute as under review (admin only)",
     *     tags={"Admin Disputes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Dispute marked as under review"),
     *     @OA\Response(response=404, description="Dispute not found")
     * )
     */
    public function adminReview($id)
    {
        $dispute = Dispute::findOrFail($id);

        if ($dispute->markAsUnderReview()) {
            return response()->json([
                'message' => 'Dispute marked as under review',
                'dispute' => $dispute->fresh(['collaborationRequest', 'initiator', 'respondent'])
            ]);
        }

        return response()->json(['error' => 'Cannot mark dispute as under review'], 422);
    }
} 