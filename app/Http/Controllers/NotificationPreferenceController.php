<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Notification Preferences",
 *     description="API Endpoints for managing notification preferences"
 * )
 */
class NotificationPreferenceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/notification-preferences",
     *     summary="Get user's notification preferences",
     *     description="Returns the notification preferences for the authenticated user",
     *     operationId="getNotificationPreferences",
     *     tags={"Notification Preferences"},
     *     security={{ "sanctum": {} }},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="user_id", type="integer"),
     *             @OA\Property(property="Collaboration_Requests", type="boolean"),
     *             @OA\Property(property="Messages", type="boolean"),
     *             @OA\Property(property="Application_Updates", type="boolean"),
     *             @OA\Property(property="Marketing_Emails", type="boolean"),
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show(Request $request): JsonResponse
    {
        $preferences = $request->user()->notificationPreferences 
            ?? NotificationPreference::create(['user_id' => $request->user()->id]);

        return response()->json($preferences);
    }

    /**
     * @OA\Put(
     *     path="/api/notification-preferences",
     *     summary="Update notification preferences",
     *     description="Updates the notification preferences for the authenticated user",
     *     operationId="updateNotificationPreferences",
     *     tags={"Notification Preferences"},
     *     security={{ "sanctum": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="Collaboration_Requests", type="boolean"),
     *             @OA\Property(property="Messages", type="boolean"),
     *             @OA\Property(property="Application_Updates", type="boolean"),
     *             @OA\Property(property="Marketing_Emails", type="boolean"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="user_id", type="integer"),
     *             @OA\Property(property="Collaboration_Requests", type="boolean"),
     *             @OA\Property(property="Messages", type="boolean"),
     *             @OA\Property(property="Application_Updates", type="boolean"),
     *             @OA\Property(property="Marketing_Emails", type="boolean"),
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'collaboration_requests' => 'boolean',
            'messages' => 'boolean',
            'application_updates' => 'boolean',
            'marketing_emails' => 'boolean',
        ]);

        $preferences = $request->user()->notificationPreferences;

        if (!$preferences) {
            $preferences = NotificationPreference::create([
                'user_id' => $request->user()->id,
                ...$validated
            ]);
        } else {
            $preferences->update($validated);
        }

        return response()->json($preferences);
    }
} 