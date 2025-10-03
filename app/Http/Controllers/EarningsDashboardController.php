<?php

namespace App\Http\Controllers;

use App\Models\StripeTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EarningsDashboardController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/earnings",
     *     summary="Get user's earnings dashboard data",
     *     tags={"Earnings"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Earnings data retrieved"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        $user = Auth::user();
        
        $totalEarned = StripeTransaction::where('user_id', $user->id)
            ->where('status', 'succeeded')
            ->sum('amount');

        $pendingPayouts = StripeTransaction::where('user_id', $user->id)
            ->where('status', 'pending')
            ->sum('amount');

        $completedPayouts = StripeTransaction::where('user_id', $user->id)
            ->where('type', 'transfer')
            ->where('status', 'succeeded')
            ->sum('amount');

        $recentTransactions = StripeTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return response()->json([
            'total_earned' => $totalEarned,
            'pending_payouts' => $pendingPayouts,
            'completed_payouts' => $completedPayouts,
            'recent_transactions' => $recentTransactions,
            'stripe_dashboard_url' => "https://dashboard.stripe.com/{$user->stripe_account_id}",
            'account_status' => [
                'charges_enabled' => $user->charges_enabled,
                'payouts_enabled' => $user->payouts_enabled,
                'status' => $user->stripe_account_status
            ]
        ]);
    }
} 