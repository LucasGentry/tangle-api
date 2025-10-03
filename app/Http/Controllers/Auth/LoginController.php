<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/login",
     *     summary="Authenticate user and create token",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="John@webstacks.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Login successful"),
     *     @OA\Response(response=422, description="Invalid credentials")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'message' => 'Invalid email address.'
            ], 404);
        }

        if (! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Incorrect password.'
            ], 401);
        }

        // Example: check if account is locked (adjust field as needed)
        if (isset($user->locked) && $user->locked) {
            return response()->json([
                'message' => 'Account is locked. Please contact support.'
            ], 423); // 423 Locked
        }

        // Auth::login($user);
        
        return response()->json([
            'token' => $user->createToken('auth_token')->plainTextToken,
            'user_id' => $user->id,
        ]);
    }
}
