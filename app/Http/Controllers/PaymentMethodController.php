<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentMethod as StripePaymentMethod;
use App\Services\StripeService;

/**
 * @OA\Tag(
 *     name="Payments",
 *     description="Manage payment methods and payout settings"
 * )
 */
class PaymentMethodController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * @OA\Get(
     *     path="/api/payment-methods",
     *     summary="List all payment methods for the authenticated user",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of payment methods",
     *         @OA\JsonContent(type="array", @OA\Items(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="brand", type="string"),
     *             @OA\Property(property="last4", type="string"),
     *             @OA\Property(property="exp_month", type="integer"),
     *             @OA\Property(property="exp_year", type="integer")
     *         ))
     *     )
     * )
     */
    public function index(Request $request)
    {
        $user = $request->user();
        return response()->json($user->paymentMethods);
    }

    /**
     * @OA\Post(
     *     path="/api/payment-methods",
     *     summary="Add a new payment method",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"payment_method_id"},
     *             @OA\Property(property="payment_method_id", type="string", example="pm_1N2Yw2L8r9QeXQ")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment method added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Payment method added successfully")
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
    public function store(Request $request)
    {
        $request->validate([
            'payment_method_id' => 'required|string',
        ]);

        $user = $request->user();

        // Use the service to attach the payment method
        $stripePaymentMethod = $this->stripeService->attachPaymentMethodToCustomer($user, $request->payment_method_id);

        // Save to DB
        $user->paymentMethods()->create([
            'stripe_payment_method_id' => $stripePaymentMethod->id,
            'brand' => $stripePaymentMethod->card->brand,
            'last4' => $stripePaymentMethod->card->last4,
            'exp_month' => $stripePaymentMethod->card->exp_month,
            'exp_year' => $stripePaymentMethod->card->exp_year,
            'cvc' => $stripePaymentMethod->card->cvc, // Note: CVC is not stored in Stripe, this is just for example
        ]);

        return response()->json(['
            message' => 'Payment method added successfully',
            'payment_method' => [
                'id' => $stripePaymentMethod->id,
                'brand' => $stripePaymentMethod->card->brand,
                'last4' => $stripePaymentMethod->card->last4,
                'exp_month' => $stripePaymentMethod->card->exp_month,
                'exp_year' => $stripePaymentMethod->card->exp_year,
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/payment-methods/{id}",
     *     summary="Remove a payment method",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment method removed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Payment method removed successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Payment method not found"
     *     )
     * )
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $paymentMethod = $user->paymentMethods()->findOrFail($id);

        // Detach from Stripe customer
        try {
            $stripePaymentMethod = StripePaymentMethod::retrieve($paymentMethod->stripe_payment_method_id);
            $stripePaymentMethod->detach();
        } catch (\Exception $e) {
            Log::error('Stripe detach error', ['error' => $e->getMessage()]);
        }

        $paymentMethod->delete();
        return response()->json(['message' => 'Payment method removed successfully']);
    }
}
