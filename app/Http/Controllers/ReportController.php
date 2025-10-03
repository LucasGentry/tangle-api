<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\CollaborationRequest;
use App\Models\User;
use App\Models\Message;
use App\Models\Review;
use App\Models\Application;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * @OA\Post(
     *     path="/api/reports",
     *     summary="Create a new report",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reportable_type", "reportable_id", "reason"},
     *             @OA\Property(property="reportable_type", type="string", enum={"collaboration_request", "user", "message", "review", "application"}),
     *             @OA\Property(property="reportable_id", type="integer"),
     *             @OA\Property(property="reason", type="string", enum={"spam", "scam", "offensive", "fake_opportunity", "inappropriate", "harassment", "other"}),
     *             @OA\Property(property="comment", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Report created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reportable_type' => ['required', Rule::in([
                'collaboration_request',
                'user',
                'message',
                'review',
                'application'
            ])],
            'reportable_id' => 'required|integer',
            'reason' => ['required', Rule::in([
                Report::REASON_SPAM,
                Report::REASON_SCAM,
                Report::REASON_OFFENSIVE,
                Report::REASON_FAKE_OPPORTUNITY,
                Report::REASON_INAPPROPRIATE,
                Report::REASON_HARASSMENT,
                Report::REASON_OTHER
            ])],
            'comment' => 'nullable|string|max:1000'
        ]);

        // Check if reportable content exists
        $reportable = $this->getReportableContent($validated['reportable_type'], $validated['reportable_id']);
        if (!$reportable) {
            return response()->json(['error' => 'Reported content not found'], 404);
        }

        // Check if user is reporting themselves
        if ($validated['reportable_type'] === 'user' && $validated['reportable_id'] === Auth::id()) {
            return response()->json(['error' => 'You cannot report yourself'], 422);
        }

        // Check if user already reported this content
        $existingReport = Report::where('reporter_id', Auth::id())
            ->where('reportable_type', $validated['reportable_type'])
            ->where('reportable_id', $validated['reportable_id'])
            ->first();

        if ($existingReport) {
            return response()->json(['error' => 'You have already reported this content'], 422);
        }

        DB::beginTransaction();
        try {
            $report = Report::create([
                'reporter_id' => Auth::id(),
                'reportable_type' => $validated['reportable_type'],
                'reportable_id' => $validated['reportable_id'],
                'reason' => $validated['reason'],
                'comment' => $validated['comment']
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Report submitted successfully',
                'report' => $report->load('reportable')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create report', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to submit report'], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/reports",
     *     summary="Get user's reports",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="List of user's reports")
     * )
     */
    public function index(Request $request)
    {
        $query = Report::with('reportable')
            ->forUser(Auth::id())
            ->when($request->status, function ($q, $status) {
                return $q->where('status', $status);
            })
            ->latest();

        $reports = $query->paginate(15);

        return response()->json($reports);
    }

    /**
     * @OA\Get(
     *     path="/api/reports/{id}",
     *     summary="Get report details",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Report details"),
     *     @OA\Response(response=404, description="Report not found")
     * )
     */
    public function show($id)
    {
        $report = Report::with(['reportable', 'reporter', 'reviewedBy'])
            ->forUser(Auth::id())
            ->findOrFail($id);

        return response()->json($report);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/reports",
     *     summary="Get all reports (admin only)",
     *     tags={"Admin Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="reason",
     *         in="query",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="List of all reports")
     * )
     */
    public function adminIndex(Request $request)
    {
        $query = Report::with(['reportable', 'reporter', 'reviewedBy'])
            ->when($request->status, function ($q, $status) {
                return $q->where('status', $status);
            })
            ->when($request->reason, function ($q, $reason) {
                return $q->where('reason', $reason);
            })
            ->when($request->type, function ($q, $type) {
                return $q->where('reportable_type', $type);
            })
            ->latest();

        $reports = $query->paginate(20);

        return response()->json($reports);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/reports/{id}/review",
     *     summary="Review a report (admin only)",
     *     tags={"Admin Reports"},
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
     *             required={"action"},
     *             @OA\Property(property="action", type="string", enum={"approve", "dismiss"}),
     *             @OA\Property(property="admin_notes", type="string"),
     *             @OA\Property(property="admin_action", type="string", enum={"none", "warn", "suspend", "delete", "hide"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Report reviewed successfully"),
     *     @OA\Response(response=404, description="Report not found")
     * )
     */
    public function adminReview(Request $request, $id)
    {
        $report = Report::with(['reportable', 'reporter'])
            ->findOrFail($id);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'dismiss'])],
            'admin_notes' => 'nullable|string|max:1000',
            'admin_action' => ['nullable', Rule::in([
                Report::ACTION_NONE,
                Report::ACTION_WARN,
                Report::ACTION_SUSPEND,
                Report::ACTION_DELETE,
                Report::ACTION_HIDE
            ])]
        ]);

        DB::beginTransaction();
        try {
            if ($validated['action'] === 'approve') {
                $success = $report->approve($validated['admin_notes'], $validated['admin_action'], Auth::id());
            } else {
                $success = $report->dismiss($validated['admin_notes'], Auth::id());
            }

            if ($success) {
                // Handle admin action if specified
                if ($validated['action'] === 'approve' && $validated['admin_action']) {
                    $this->handleAdminAction($report, $validated['admin_action']);
                }

                // Notify reporter
                $this->notificationService->send(
                    $report->reporter,
                    'report_reviewed',
                    [
                        'report_id' => $report->id,
                        'action' => $validated['action'],
                        'admin_notes' => $validated['admin_notes'] ?? null
                    ]
                );

                DB::commit();

                return response()->json([
                    'message' => 'Report reviewed successfully',
                    'report' => $report->fresh(['reportable', 'reporter', 'reviewedBy'])
                ]);
            }

            DB::rollBack();
            return response()->json(['error' => 'Cannot review report in current state'], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to review report', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to review report'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/admin/reports/{id}/mark-review",
     *     summary="Mark report as under review (admin only)",
     *     tags={"Admin Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Report marked as under review"),
     *     @OA\Response(response=404, description="Report not found")
     * )
     */
    public function adminMarkReview($id)
    {
        $report = Report::findOrFail($id);

        if ($report->markAsUnderReview()) {
            return response()->json([
                'message' => 'Report marked as under review',
                'report' => $report->fresh(['reportable', 'reporter'])
            ]);
        }

        return response()->json(['error' => 'Cannot mark report as under review'], 422);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/reports/stats",
     *     summary="Get report statistics (admin only)",
     *     tags={"Admin Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Report statistics")
     * )
     */
    public function adminStats()
    {
        $stats = [
            'total_reports' => Report::count(),
            'pending_reports' => Report::pending()->count(),
            'under_review_reports' => Report::underReview()->count(),
            'resolved_reports' => Report::resolved()->count(),
            'reports_by_reason' => Report::selectRaw('reason, COUNT(*) as count')
                ->groupBy('reason')
                ->pluck('count', 'reason'),
            'reports_by_type' => Report::selectRaw('reportable_type, COUNT(*) as count')
                ->groupBy('reportable_type')
                ->pluck('count', 'reportable_type'),
            'reports_by_status' => Report::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
        ];

        return response()->json($stats);
    }

    /**
     * Get the reportable content based on type and ID
     */
    private function getReportableContent($type, $id)
    {
        return match($type) {
            'collaboration_request' => CollaborationRequest::find($id),
            'user' => User::find($id),
            'message' => Message::find($id),
            'review' => Review::find($id),
            'application' => Application::find($id),
            default => null
        };
    }

    /**
     * Handle admin actions on reported content
     */
    private function handleAdminAction(Report $report, $action)
    {
        $reportable = $report->reportable;

        if (!$reportable) return;

        switch ($action) {
            case Report::ACTION_WARN:
                // Send warning notification
                if ($reportable instanceof User) {
                    $this->notificationService->send(
                        $reportable,
                        'admin_warning',
                        [
                            'reason' => $report->reason,
                            'admin_notes' => $report->admin_notes
                        ]
                    );
                }
                break;

            case Report::ACTION_SUSPEND:
                // Suspend user
                if ($reportable instanceof User) {
                    $reportable->update(['is_suspended' => true]);
                    $this->notificationService->send(
                        $reportable,
                        'account_suspended',
                        [
                            'reason' => $report->reason,
                            'admin_notes' => $report->admin_notes
                        ]
                    );
                }
                break;

            case Report::ACTION_DELETE:
                // Delete content
                $reportable->delete();
                break;

            case Report::ACTION_HIDE:
                // Hide content
                if (method_exists($reportable, 'update')) {
                    $reportable->update(['is_hidden' => true]);
                }
                break;
        }
    }
} 