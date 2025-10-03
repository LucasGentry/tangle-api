<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Exception;
use OpenApi\Annotations as OA;

class SocialController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/auth/{provider}/redirect",
     *     summary="Get social auth URL",
     *     tags={"Authentication"},
     *     @OA\Parameter(
     *         name="provider",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", enum={"google", "twitter"})
     *     ),
     *     @OA\Response(response=200, description="Social auth URL")
     * )
     */
    public function redirect($provider)
    {
        return Socialite::driver($provider)->stateless()->redirect();
    }

    /**
     * @OA\Get(
     *     path="/api/auth/{provider}/callback",
     *     summary="Handle social auth callback",
     *     tags={"Authentication"},
     *     @OA\Parameter(
     *         name="provider",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", enum={"google", "twitter"})
     *     ),
     *     @OA\Response(response=200, description="Authentication successful"),
     *     @OA\Response(response=422, description="Invalid credentials")
     * )
     */
    public function callback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();

            $socialAccount = SocialAccount::where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            if ($socialAccount) {
                $user = $socialAccount->user;
            } else {
                $user = DB::transaction(function () use ($provider, $socialUser) {
                    // Check if user exists with same email
                    $user = User::where('email', $socialUser->getEmail())->first();

                    if (!$user) {
                        $user = User::create([
                            'name' => $socialUser->getName(),
                            'email' => $socialUser->getEmail(),
                            'password' => bcrypt(Str::random(16)),
                            'profile_photo' => $socialUser->getAvatar()
                        ]);
                    }

                    $user->socialAccounts()->create([
                        'provider' => $provider,
                        'provider_id' => $socialUser->getId(),
                        'token' => $socialUser->token,
                        'refresh_token' => $socialUser->refreshToken ?? null,
                        'expires_at' => isset($socialUser->expiresIn) ? now()->addSeconds($socialUser->expiresIn) : null
                    ]);

                    return $user;
                });
            }

            return response()->json([
                'token' => $user->createToken('auth_token')->plainTextToken,
                'user' => $user,
            ]);

        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to authenticate with ' . $provider], 422);
        }
    }
}
