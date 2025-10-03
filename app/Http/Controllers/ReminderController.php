<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Models\CollaborationRequest;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReminderController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * @OA\Get(
     *     path="/api/reminders",
     *     summary="Get user's reminders",
     *     tags={"Reminders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         @OA\Schema(type="string", enum={"pending", "sent", "cancelled", "failed"})
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         @OA\Schema(type="string", enum={"day_3", "day_7", "day_14", "auto_dispute"})
     *     ),
     *     @OA\Response(response=200, description="List of reminders")
     * )
     */
    public function index(Request $request)
    {
        $query = Reminder::with(['collaborationRequest'])
            ->forUser(Auth::id())
            ->when($request->status, function ($q, $status) {
                return $q->where('status', $status);
            })
            ->when($request->type, function ($q, $type) {
                return $q->where('type', $type);
            })
            ->latest();

        $reminders = $query->paginate(15);

        return response()->json($reminders);
    }

    /**
     * @OA\Get(
     *     path="/api/reminders/{id}",
     *     summary="Get reminder details",
     *     tags={"Reminders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Reminder details"),
     *     @OA\Response(response=404, description="Reminder not found")
     * )
     */
    public function show($id)
    {
        $reminder = Reminder::with(['collaborationRequest', 'user'])
            ->forUser(Auth::id())
            ->findOrFail($id);

        return response()->json($reminder);
    }

    /**
     * @OA\Post(
     *     path="/api/reminders/{id}/dismiss",
     *     summary="Dismiss a reminder",
     *     tags={"Reminders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Reminder dismissed successfully"),
     *     @OA\Response(response=404, description="Reminder not found")
     * )
     */
    public function dismiss($id)
    {
        $reminder = Reminder::where('user_id', Auth::id())
            ->where('id', $id)
            ->where('status', Reminder::STATUS_PENDING)
            ->firstOrFail();

        if ($reminder->cancel()) {
            return response()->json([
                'message' => 'Reminder dismissed successfully',
                'reminder' => $reminder->fresh(['collaborationRequest'])
            ]);
        }

        return response()->json(['error' => 'Cannot dismiss reminder'], 422);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/reminders",
     *     summary="Get all reminders (admin only)",
     *     tags={"Admin Reminders"},
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
     *     @OA\Parameter(
     *         name="due",
     *         in="query",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(response=200, description="List of all reminders")
     * )
     */
    public function adminIndex(Request $request)
    {
        $query = Reminder::with(['collaborationRequest', 'user'])
            ->when($request->status, function ($q, $status) {
                return $q->where('status', $status);
            })
            ->when($request->type, function ($q, $type) {
                return $q->where('type', $type);
            })
            ->when($request->due, function ($q, $due) {
                return $due ? $q->due() : $q;
            })
            ->latest();

        $reminders = $query->paginate(20);

        return response()->json($reminders);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/reminders/send-due",
     *     summary="Send all due reminders (admin only)",
     *     tags={"Admin Reminders"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Due reminders sent successfully")
     * )
     */
    public function adminSendDue()
    {
        $dueReminders = Reminder::due()->with(['collaborationRequest', 'user'])->get();
        $sentCount = 0;
        $failedCount = 0;

        foreach ($dueReminders as $reminder) {
            try {
                $this->sendReminder($reminder);
                $sentCount++;
            } catch (\Exception $e) {
                Log::error('Failed to send reminder', [
                    'reminder_id' => $reminder->id,
                    'error' => $e->getMessage()
                ]);
                $reminder->markAsFailed();
                $failedCount++;
            }
        }

        return response()->json([
            'message' => 'Due reminders processed',
            'sent' => $sentCount,
            'failed' => $failedCount,
            'total' => $dueReminders->count()
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/reminders/{id}/send",
     *     summary="Send a specific reminder (admin only)",
     *     tags={"Admin Reminders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Reminder sent successfully"),
     *     @OA\Response(response=404, description="Reminder not found")
     * )
     */
    public function adminSend($id)
    {
        $reminder = Reminder::with(['collaborationRequest', 'user'])
            ->findOrFail($id);

        if (!$reminder->isPending()) {
            return response()->json(['error' => 'Reminder is not pending'], 422);
        }

        try {
            $this->sendReminder($reminder);
            return response()->json([
                'message' => 'Reminder sent successfully',
                'reminder' => $reminder->fresh(['collaborationRequest', 'user'])
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send reminder', [
                'reminder_id' => $reminder->id,
                'error' => $e->getMessage()
            ]);
            $reminder->markAsFailed();
            return response()->json(['error' => 'Failed to send reminder'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/admin/reminders/{id}/cancel",
     *     summary="Cancel a reminder (admin only)",
     *     tags={"Admin Reminders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Reminder cancelled successfully"),
     *     @OA\Response(response=404, description="Reminder not found")
     * )
     */
    public function adminCancel($id)
    {
        $reminder = Reminder::findOrFail($id);

        if ($reminder->cancel()) {
            return response()->json([
                'message' => 'Reminder cancelled successfully',
                'reminder' => $reminder->fresh(['collaborationRequest', 'user'])
            ]);
        }

        return response()->json(['error' => 'Cannot cancel reminder'], 422);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/reminders/schedule",
     *     summary="Schedule reminders for a collaboration (admin only)",
     *     tags={"Admin Reminders"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"collaboration_request_id", "user_id"},
     *             @OA\Property(property="collaboration_request_id", type="integer"),
     *             @OA\Property(property="user_id", type="integer"),
     *             @OA\Property(property="types", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Reminders scheduled successfully")
     * )
     */
    public function adminSchedule(Request $request)
    {
        $validated = $request->validate([
            'collaboration_request_id' => 'required|exists:collaboration_requests,id',
            'user_id' => 'required|exists:users,id',
            'types' => 'nullable|array',
            'types.*' => 'string|in:day_3,day_7,day_14,auto_dispute'
        ]);

        $types = $validated['types'] ?? [Reminder::TYPE_DAY_3, Reminder::TYPE_DAY_7, Reminder::TYPE_DAY_14];
        $scheduledReminders = [];

        DB::beginTransaction();
        try {
            foreach ($types as $type) {
                $reminder = Reminder::createForCollaboration(
                    $validated['collaboration_request_id'],
                    $validated['user_id'],
                    $type
                );
                $scheduledReminders[] = $reminder;
            }

            DB::commit();

            return response()->json([
                'message' => 'Reminders scheduled successfully',
                'reminders' => Reminder::with(['collaborationRequest', 'user'])
                    ->whereIn('id', collect($scheduledReminders)->pluck('id'))
                    ->get()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to schedule reminders', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to schedule reminders'], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/reminders/stats",
     *     summary="Get reminder statistics (admin only)",
     *     tags={"Admin Reminders"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Reminder statistics")
     * )
     */
    public function adminStats()
    {
        $stats = [
            'total_reminders' => Reminder::count(),
            'pending_reminders' => Reminder::pending()->count(),
            'sent_reminders' => Reminder::sent()->count(),
            'cancelled_reminders' => Reminder::cancelled()->count(),
            'due_reminders' => Reminder::due()->count(),
            'reminders_by_type' => Reminder::selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),
            'reminders_by_status' => Reminder::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
        ];

        return response()->json($stats);
    }

    /**
     * Send a reminder notification
     */
    private function sendReminder(Reminder $reminder)
    {
        // Generate message if not set
        if (!$reminder->message) {
            $reminder->update(['message' => $reminder->default_message]);
        }

        // Send notification
        $this->notificationService->send(
            $reminder->user,
            'collaboration_reminder',
            [
                'reminder_id' => $reminder->id,
                'collaboration_title' => $reminder->collaborationRequest->title,
                'type' => $reminder->type,
                'message' => $reminder->message,
                'days_since_in_progress' => $reminder->days_since_in_progress
            ]
        );

        // Mark as sent
        $reminder->markAsSent();

        // If this is a day 14 reminder and collaboration is still in progress, auto-open dispute
        if ($reminder->type === Reminder::TYPE_DAY_14) {
            $this->handleAutoDispute($reminder);
        }
    }

    /**
     * Handle auto-dispute creation for day 14 reminders
     */
    private function handleAutoDispute(Reminder $reminder)
    {
        $collaboration = $reminder->collaborationRequest;
        
        // Check if collaboration is still in progress
        if ($collaboration->status === CollaborationRequest::STATUS_IN_PROGRESS) {
            // Check if dispute already exists
            $existingDispute = \App\Models\Dispute::where('collaboration_request_id', $collaboration->id)
                ->whereIn('status', [\App\Models\Dispute::STATUS_OPEN, \App\Models\Dispute::STATUS_UNDER_REVIEW])
                ->first();

            if (!$existingDispute) {
                // Create auto-dispute
                \App\Models\Dispute::create([
                    'collaboration_request_id' => $collaboration->id,
                    'initiator_id' => $collaboration->user_id,
                    'respondent_id' => $reminder->user_id,
                    'type' => \App\Models\Dispute::TYPE_DEADLINE,
                    'description' => 'Auto-opened dispute due to collaboration not being completed within 14 days.',
                    'status' => \App\Models\Dispute::STATUS_OPEN,
                    'auto_opened_at' => now()
                ]);

                // Send notification about auto-dispute
                $this->notificationService->send(
                    $reminder->user,
                    'auto_dispute_opened',
                    [
                        'collaboration_title' => $collaboration->title,
                        'dispute_type' => 'deadline'
                    ]
                );
            }
        }
    }
} 