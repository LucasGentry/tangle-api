<?php

namespace App\Http\Controllers;

use App\Models\CustomNotification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Notifications",
 *     description="API Endpoints for managing notifications"
 * )
 */
class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * @OA\Get(
     *     path="/api/notifications",
     *     summary="Get user notifications",
     *     description="Returns a paginated list of user notifications",
     *     operationId="getNotifications",
     *     tags={"Notifications"},
     *     security={{ "sanctum": {} }},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="string", format="uuid"),
     *                     @OA\Property(property="type", type="string"),
     *                     @OA\Property(property="data", type="object"),
     *                     @OA\Property(property="read_at", type="string", format="datetime", nullable=true),
     *                     @OA\Property(property="created_at", type="string", format="datetime")
     *                 )
     *             ),
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="per_page", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = CustomNotification::where('notifiable_id', $request->user()->id)
            ->where('notifiable_type', get_class($request->user()))
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($notifications);
    }

    /**
     * @OA\Get(
     *     path="/api/notifications/unread",
     *     summary="Get unread notifications count",
     *     description="Returns the count of unread notifications for the authenticated user",
     *     operationId="getUnreadCount",
     *     tags={"Notifications"},
     *     security={{ "sanctum": {} }},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="unread_count", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function unread(Request $request): JsonResponse
    {
        $count = $this->notificationService->getUnreadCount($request->user());
        return response()->json(['unread_count' => $count]);
    }

    /**
     * @OA\Post(
     *     path="/api/notifications/{notification}/read",
     *     summary="Mark notification as read",
     *     description="Marks a specific notification as read",
     *     operationId="markNotificationAsRead",
     *     tags={"Notifications"},
     *     security={{ "sanctum": {} }},
     *     @OA\Parameter(
     *         name="notification",
     *         in="path",
     *         description="Notification ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function markAsRead(Request $request, CustomNotification $notification): JsonResponse
    {
        if ($notification->notifiable_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->notificationService->markAsRead($notification);
        return response()->json(['message' => 'Notification marked as read']);
    }

    /**
     * @OA\Post(
     *     path="/api/notifications/read-all",
     *     summary="Mark all notifications as read",
     *     description="Marks all notifications as read for the authenticated user",
     *     operationId="markAllNotificationsAsRead",
     *     tags={"Notifications"},
     *     security={{ "sanctum": {} }},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $this->notificationService->markAllAsRead($request->user());
        return response()->json(['message' => 'All notifications marked as read']);
    }

    /**
     * @OA\Delete(
     *     path="/api/notifications/{notification}",
     *     summary="Delete notification",
     *     description="Deletes a specific notification",
     *     operationId="deleteNotification",
     *     tags={"Notifications"},
     *     security={{ "sanctum": {} }},
     *     @OA\Parameter(
     *         name="notification",
     *         in="path",
     *         description="Notification ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function delete(Request $request, CustomNotification $notification): JsonResponse
    {
        if ($notification->notifiable_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notification->delete();
        return response()->json(['message' => 'Notification deleted']);
    }
} 