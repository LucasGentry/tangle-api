<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Review;
use App\Models\Message;
use App\Models\Application;
use App\Models\CollaborationRequest;
use App\Models\StripeTransaction;
use App\Models\AdminLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'total_users' => User::count(),
            'total_collaborations' => CollaborationRequest::count(),
            'total_applications' => Application::count(),
            'flagged_reviews' => Review::where('is_flagged', true)->count(),
            'pending_reports' => \App\Models\Report::pending()->count(),
            'open_disputes' => \App\Models\Dispute::open()->count(),
            'due_reminders' => \App\Models\Reminder::due()->count(),
            'pending_reports_by_type' => \App\Models\Report::selectRaw('reportable_type, COUNT(*) as count')
                ->pending()
                ->groupBy('reportable_type')
                ->pluck('count', 'reportable_type'),
            'disputes_by_type' => \App\Models\Dispute::selectRaw('type, COUNT(*) as count')
                ->open()
                ->groupBy('type')
                ->pluck('count', 'type'),
            'reminders_by_type' => \App\Models\Reminder::selectRaw('type, COUNT(*) as count')
                ->pending()
                ->groupBy('type')
                ->pluck('count', 'type'),
        ];

        return response()->json($stats);
    }

    public function users(Request $request)
    {
        $users = User::with('reviews')
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->paginate(15);

        return response()->json($users);
    }

    public function updateUserStatus(Request $request, User $user)
    {
        $request->validate([
            'action' => 'required|in:suspend,reinstate,delete',
            'reason' => 'required|string|max:500'
        ]);

        DB::beginTransaction();
        try {
            switch ($request->action) {
                case 'suspend':
                    $user->update(['is_suspended' => true]);
                    break;
                case 'reinstate':
                    $user->update(['is_suspended' => false]);
                    break;
                case 'delete':
                    $user->delete();
                    break;
            }

            // Log admin action
            $this->logAdminAction($request->user(), 'user_status_update', [
                'user_id' => $user->id,
                'action' => $request->action,
                'reason' => $request->reason
            ]);

            DB::commit();
            return response()->json(['message' => 'User status updated successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin user status update failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to update user status'], 500);
        }
    }

    public function moderateContent(Request $request)
    {
        $request->validate([
            'type' => 'required|in:message,review,collaboration',
            'id' => 'required|integer',
            'action' => 'required|in:hide,unhide,delete',
            'reason' => 'required|string|max:500'
        ]);

        DB::beginTransaction();
        try {
            $content = match($request->type) {
                'message' => Message::findOrFail($request->id),
                'review' => Review::findOrFail($request->id),
                'collaboration' => CollaborationRequest::findOrFail($request->id),
            };

            switch ($request->action) {
                case 'hide':
                    $content->update(['is_hidden' => true]);
                    break;
                case 'unhide':
                    $content->update(['is_hidden' => false]);
                    break;
                case 'delete':
                    $content->delete();
                    break;
            }

            // Log admin action
            $this->logAdminAction($request->user(), 'content_moderation', [
                'content_type' => $request->type,
                'content_id' => $request->id,
                'action' => $request->action,
                'reason' => $request->reason
            ]);

            DB::commit();
            return response()->json(['message' => 'Content moderated successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Content moderation failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to moderate content'], 500);
        }
    }

    public function handleCollaborationRequest(Request $request, CollaborationRequest $collaborationRequest)
    {
        $request->validate([
            'action' => 'required|in:cancel,update',
            'reason' => 'required|string|max:500',
            'updates' => 'required_if:action,update|array'
        ]);

        DB::beginTransaction();
        try {
            if ($request->action === 'cancel') {
                $collaborationRequest->update(['status' => CollaborationRequest::STATUS_CANCELLED]);
            } else {
                $collaborationRequest->update($request->updates);
            }

            // Log admin action
            $this->logAdminAction($request->user(), 'collaboration_request_update', [
                'collaboration_id' => $collaborationRequest->id,
                'action' => $request->action,
                'reason' => $request->reason,
                'updates' => $request->updates ?? null
            ]);

            DB::commit();
            return response()->json(['message' => 'Collaboration request updated successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Collaboration request update failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to update collaboration request'], 500);
        }
    }

    public function resolveDispute(Request $request)
    {
        $request->validate([
            'dispute_id' => 'required|integer',
            'resolution' => 'required|string|max:1000',
            'action' => 'required|in:refund,release_payment,cancel',
            'reason' => 'required|string|max:500'
        ]);

        DB::beginTransaction();
        try {
            // Handle dispute resolution logic here
            // This would involve updating the relevant transaction status
            // and potentially triggering refunds or payment releases via Stripe

            // Log admin action
            $this->logAdminAction($request->user(), 'dispute_resolution', [
                'dispute_id' => $request->dispute_id,
                'resolution' => $request->resolution,
                'action' => $request->action,
                'reason' => $request->reason
            ]);

            DB::commit();
            return response()->json(['message' => 'Dispute resolved successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Dispute resolution failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to resolve dispute'], 500);
        }
    }

    public function paymentLogs(Request $request)
    {
        $transactions = StripeTransaction::with(['user'])
            ->when($request->date_from, function($query, $date) {
                $query->where('created_at', '>=', $date);
            })
            ->when($request->date_to, function($query, $date) {
                $query->where('created_at', '<=', $date);
            })
            ->when($request->type, function($query, $type) {
                $query->where('type', $type);
            })
            ->paginate(20);

        return response()->json($transactions);
    }

    public function adminLogs(Request $request)
    {
        $logs = AdminLog::with(['admin'])
            ->when($request->action_type, function($query, $type) {
                $query->where('action_type', $type);
            })
            ->when($request->admin_id, function($query, $adminId) {
                $query->where('admin_id', $adminId);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($logs);
    }

    private function logAdminAction($admin, $actionType, $data)
    {
        AdminLog::create([
            'admin_id' => $admin->id,
            'action_type' => $actionType,
            'action_data' => $data,
            'ip_address' => request()->ip()
        ]);
    }
} 