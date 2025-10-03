<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

class ProfileController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     summary="Get user profile",
     *     tags={"Profile"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="User profile"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function show($id)
    {
        $user = User::with(['followers', 'following'])->findOrFail($id);
        
        return response()->json([
            'user' => $user,
            'followers_count' => $user->followers->count(),
            'following_count' => $user->following->count(),
            'is_following' => Auth::check() ? Auth::user()->isFollowing($user) : false
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/users/{id}/follow",
     *     summary="Follow a user",
     *     tags={"Profile"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Follow successful"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function follow($id)
    {
        $userToFollow = User::findOrFail($id);
        $user = Auth::user();

        $user->follow($userToFollow);

        return response()->json([
            'message' => 'Successfully followed user',
            'is_following' => true
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{id}/unfollow",
     *     summary="Unfollow a user",
     *     tags={"Profile"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Unfollow successful"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function unfollow($id)
    {
        $userToUnfollow = User::findOrFail($id);
        $user = Auth::user();

        $user->unfollow($userToUnfollow);

        return response()->json([
            'message' => 'Successfully unfollowed user',
            'is_following' => false
        ]);
    }

    /**
     * Update basic profile information
     * 
     * @OA\Post(
     *     path="/api/profile",
     *     summary="Update user's basic profile information",
     *     tags={"Profile"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"first_name", "last_name", "location", "bio"},
     *                 @OA\Property(property="first_name", type="string", example="John"),
     *                 @OA\Property(property="last_name", type="string", example="Doe"),
     *                 @OA\Property(property="username", type="string", example="@JohnDoe"),
     *                 @OA\Property(property="email", type="email", example="JohnDoe@gmail.com"),
     *                 @OA\Property(property="tagline", type="string", example="Developer Designer"),
     *                 @OA\Property(property="location", type="string", example="London, UK"),
     *                 @OA\Property(property="bio", type="string", example="A brief introduction about yourself", maxLength=500),
     *                 @OA\Property(property="profile_photo", type="string", format="binary"),
     *                 @OA\Property(
     *                     property="images[]",
     *                     type="array",
     *                     description="Exactly 3 images required. Max 5MB each. Supported formats: jpeg, png, jpg",
     *                     @OA\Items(type="string", format="binary"),
     *                     minItems=0,
     *                     maxItems=3
     *                 ),
     *                 @OA\Property(
     *                      property="social_media",
     *                      type="object",
     *                      @OA\Property(property="instagram", type="string", format="url", nullable=true, example="https://instagram.com/username"),
     *                      @OA\Property(property="spotify", type="string", format="url", nullable=true, example="https://spotify.com/artist/username"),
     *                      @OA\Property(property="tiktok", type="string", format="url", nullable=true, example="https://tiktok.com/@username"),
     *                      @OA\Property(property="twitch", type="string", format="url", nullable=true, example="https://twitch.tv/username"),
     *                      @OA\Property(property="youtube", type="string", format="url", nullable=true, example="https://youtube.com/c/username")
     *                  )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Profile updated successfully"),
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function update(Request $request)
    {
        // If social_media is a string (from form-data), decode it
        if (is_string($request->social_media)) {
            $request->merge([
                'social_media' => json_decode($request->social_media, true)
            ]);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'string|max:255',
            'email' => 'email|max:255',
            'tagline' => 'string|max:255',
            'location' => 'required|string|max:255',
            'bio' => 'required|string|max:500',
            'profile_photo' => 'nullable|image|max:2048', // 2MB max
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120', // 5MB max
            'images' => 'nullable|array|min:0|max:3', // Exactly 3 images
            'social_media' => 'required|array',
            'social_media.instagram' => 'nullable|string|url',
            'social_media.spotify' => 'nullable|string|url',
            'social_media.tiktok' => 'nullable|string|url',
            'social_media.twitch' => 'nullable|string|url',
            'social_media.youtube' => 'nullable|string|url'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $updateData = [
            'name' => $request->first_name . ' ' . $request->last_name,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'location' => $request->location,
            'bio' => $request->bio,
            'username' => $request->username,
            'email' => $request->email,
            'tagline' => $request->tagline,
        ];

        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            // Delete old photo if exists
            if ($user->profile_photo) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $user->profile_photo));
            }

            $image = $request->file('profile_photo');
            $manager = new ImageManager(new GdDriver());
            $img = $manager->read($image->getRealPath())->resize(300, 300);

            $filename = 'profile-photos/' . uniqid() . '.webp';
            Storage::disk('public')->put($filename, $img->toWebp());
            $updateData['profile_photo'] = asset(Storage::url($filename));
        }

        $portfolioImages = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $manager = new ImageManager(new GdDriver());
                $img = $manager->read($image->getRealPath());
                $filename = 'portfolio/' . uniqid() . '.webp';
                Storage::disk('public')->put($filename, $img->toWebp());
                $portfolioImages[] = [
                    'path' => $filename,
                    'url' => asset(Storage::url($filename)),
                    'size' => Storage::disk('public')->size($filename),
                    'mime_type' => 'image/webp'
                ];
            }
        }

        // Delete old portfolio images if they exist
        if ($user->portfolio_images) {
            foreach ($user->portfolio_images as $oldImage) {
                Storage::disk('public')->delete($oldImage['path']);
            }
        }

        $user->update($updateData);
        $user->update(['portfolio_images' => $portfolioImages]);
        $user->update([
            'social_media' => array_filter($request->social_media) // Remove empty values
        ]);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    /**
     * Get profile completion status
     * 
     * @OA\Get(
     *     path="/api/profile/completion",
     *     summary="Get user's profile completion status",
     *     tags={"Profile"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Profile completion status",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="completion_status",
     *                 type="object",
     *                 @OA\Property(property="basic_info", type="boolean", example=true),
     *                 @OA\Property(property="portfolio", type="boolean", example=false),
     *                 @OA\Property(property="social_media", type="boolean", example=true)
     *             ),
     *             @OA\Property(property="completion_percentage", type="number", format="float", example=66.67)
     *         )
     *     )
     * )
     */
    public function getProfileStatus(Request $request)
    {
        $user = $request->user();
        $completionStatus = [
            'basic_info' => !empty($user->first_name) && !empty($user->last_name) && !empty($user->location) && !empty($user->bio),
            'portfolio' => !empty($user->portfolio_images) && count($user->portfolio_images) >= 3,
            'social_media' => !empty($user->social_media) && count(array_filter($user->social_media)) > 0
        ];

        $completionPercentage = (collect($completionStatus)->filter()->count() / count($completionStatus)) * 100;

        return response()->json([
            'completion_status' => $completionStatus,
            'completion_percentage' => $completionPercentage
        ]);
    }

    /**
     * Change user password
     *
     * @OA\Post(
     *     path="/api/profile/change-password",
     *     summary="Change user password",
     *     tags={"Profile"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"old_password", "new_password", "new_password_confirmation"},
     *             @OA\Property(property="old_password", type="string", format="password", example="oldpassword123"),
     *             @OA\Property(property="new_password", type="string", format="password", example="newpassword456"),
     *             @OA\Property(property="new_password_confirmation", type="string", format="password", example="newpassword456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password changed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Password changed successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Old password incorrect",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Old password is incorrect")
     *         )
     *     )
     * )
     */
    public function managePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();

        if (!\Illuminate\Support\Facades\Hash::check($request->old_password, $user->password)) {
            return response()->json(['message' => 'Old password is incorrect'], 400);
        }

        $user->password = \Illuminate\Support\Facades\Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password changed successfully']);
    }

    /**
     * Get or update privacy and visibility controls for the authenticated user
     *
     * @OA\Get(
     *     path="/api/profile/privacy-visibility-controls",
     *     summary="Get privacy and visibility controls",
     *     tags={"Profile"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Current privacy and visibility settings",
     *         @OA\JsonContent(
     *             @OA\Property(property="profile_visibility", type="string"),
     *             @OA\Property(property="media_visibility", type="string"),
     *             @OA\Property(property="social_accounts_visibility", type="string"),
     *             @OA\Property(property="social_accounts_audience", type="string")
     *         )
     *     )
     * )
     *
     * @OA\Post(
     *     path="/api/profile/privacy-visibility-controls",
     *     summary="Set privacy and visibility controls",
     *     tags={"Profile"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"profile_visibility", "media_visibility", "social_accounts_visibility", "social_accounts_audience"},
     *             @OA\Property(property="profile_visibility", type="string", example="public"),
     *             @OA\Property(property="media_visibility", type="string", example="public"),
     *             @OA\Property(property="social_accounts_visibility", type="string", example="public"),
     *             @OA\Property(property="social_accounts_audience", type="string", example="everyone")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Settings updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Privacy and visibility settings updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function Privacy_VisibilityControls(Request $request)
    {
        $user = $request->user();
        if ($request->isMethod('get')) {
            $settings = $user->privacyVisibilityControls;
            return response()->json($settings);
        }

        $validated = $request->validate([
            'profile_visibility' => 'required|string',
            'media_visibility' => 'required|string',
            'social_accounts_visibility' => 'required|string',
            'social_accounts_audience' => 'required|string',
        ]);

        $settings = $user->privacyVisibilityControls;
        if ($settings) {
            $settings->update($validated);
        } else {
            $settings = $user->privacyVisibilityControls()->create($validated);
        }

        return response()->json(['message' => 'Privacy and visibility settings updated successfully']);
    }
}
