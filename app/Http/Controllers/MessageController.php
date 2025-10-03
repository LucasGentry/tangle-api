<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="Messages",
 *     description="API Endpoints for chat messages"
 * )
 */
class MessageController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/messages",
     *     tags={"Messages"},
     *     summary="Get chat messages with a user",
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="content", type="string"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="sender", type="object"),
     *                 @OA\Property(property="receiver", type="object")
     *             )
     *         )
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $messages = Message::where(function($query) use ($request) {
            $query->where('sender_id', Auth::id())
                  ->where('receiver_id', $request->user_id);
        })->orWhere(function($query) use ($request) {
            $query->where('sender_id', $request->user_id)
                  ->where('receiver_id', Auth::id());
        })
        ->with(['sender:id,name', 'receiver:id,name'])
        ->orderBy('created_at', 'asc')
        ->get();

        return response()->json($messages);
    }

    /**
     * @OA\Post(
     *     path="/api/messages",
     *     tags={"Messages"},
     *     summary="Send a new message",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="receiver_id", type="integer"),
     *                 @OA\Property(property="content", type="string"),
     *                 @OA\Property(property="attachment", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Message sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="content", type="string"),
     *             @OA\Property(property="attachment_path", type="string", nullable=true),
     *             @OA\Property(property="status", type="string", enum={"sent"})
     *         )
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'content' => 'required_without:attachment|string',
            'attachment' => 'nullable|file|max:10240', // 10MB max
        ]);

        $message = new Message();
        $message->sender_id = Auth::id();
        $message->receiver_id = $request->receiver_id;
        $message->content = $request->content ?? '';

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('attachments', 'public');
            $message->attachment_path = $path;
            $message->attachment_type = $file->getMimeType();
        }

        $message->save();

        // Broadcast the message
        broadcast(new MessageSent($message))->toOthers();

        return response()->json($message->load(['sender:id,name', 'receiver:id,name']), 201);
    }

    /**
     * @OA\Patch(
     *     path="/api/messages/{message}/status",
     *     tags={"Messages"},
     *     summary="Update message status",
     *     @OA\Parameter(
     *         name="message",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", enum={"delivered", "read"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status updated"
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function updateStatus(Message $message, Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:delivered,read'
        ]);

        if ($message->receiver_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $message->status = $request->status;
        $message->save();

        return response()->json($message);
    }

    /**
     * @OA\Post(
     *     path="/api/messages/typing",
     *     tags={"Messages"},
     *     summary="Send typing indicator",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="receiver_id", type="integer"),
     *             @OA\Property(property="is_typing", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Typing indicator sent"
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function typing(Request $request): JsonResponse
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'is_typing' => 'required|boolean'
        ]);

        broadcast(new UserTyping(Auth::user(), $request->receiver_id, $request->is_typing))->toOthers();

        return response()->json(['success' => true]);
    }
}
