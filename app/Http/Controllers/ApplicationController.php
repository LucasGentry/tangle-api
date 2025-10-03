<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\CollaborationRequest;
use App\Models\PaymentIntent as PaymentIntentModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class ApplicationController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * @OA\Post(
     *     path="/api/applications/intent/{collaboration}",
     *     summary="Create payment intent for application",
     *     tags={"Applications"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="collaboration",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Payment intent created"),
     *     @OA\Response(response=403, description="Already applied or not allowed"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function createPaymentIntent($collaborationId)
    {
        try {
            // Find the collaboration request
            $collaboration = CollaborationRequest::findOrFail($collaborationId);
            
            // Check if collaboration is open for applications
            if ($collaboration->status !== CollaborationRequest::STATUS_OPEN) {
                return response()->json([
                    'error' => 'This collaboration is not open for applications',
                    'status' => $collaboration->status
                ], 422);
            }

            // Check if user already applied
            if ($collaboration->applications()->where('user_id', Auth::id())->exists()) {
                return response()->json([
                    'error' => 'You have already applied to this collaboration'
                ], 403);
            }

            // If no application fee, create application without payment
            if (!$collaboration->application_fee || $collaboration->application_fee <= 0) {
                $application = Application::create([
                    'user_id' => Auth::id(),
                    'collaboration_request_id' => $collaborationId,
                    'status' => Application::STATUS_PENDING,
                    'payment_status' => null,
                    'payment_intent_id' => null,
                    'message' => 'Auto-created application for free collaboration'
                ]);

                return response()->json([
                    'message' => 'Application created successfully',
                    'amount' => 0,
                    'application' => $application
                ]);
            }

            //⚠⚠⚠⚠⚠ automatically PaymentMethod for testing
            // Create payment intent for paid applications
            $intent = PaymentIntent::create([
                'amount' => (int)($collaboration->application_fee * 100),
                'currency' => 'usd',
                'payment_method' => 'pm_card_visa',  // Test card <= this
                'confirm' => true,
                'metadata' => [
                    'collaboration_id' => $collaborationId,
                    'user_id' => Auth::id(),
                    'type' => 'application_fee'
                ],
                'description' => "Application fee for collaboration: {$collaboration->title}",
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never'
                ] //this
            ]);

            // Create application with pending payment status
            $application = Application::create([
                'user_id' => Auth::id(),
                'collaboration_request_id' => $collaborationId,
                'status' => Application::STATUS_PENDING,
                'payment_status' => 'pending',
                'payment_intent_id' => $intent->id,
                'message' => 'Application pending payment confirmation'
            ]);

            return response()->json([
                'clientSecret' => $intent->client_secret,
                'amount' => $collaboration->application_fee,
                'currency' => 'usd',
                'publishableKey' => config('services.stripe.key'),
                'message' => 'Application created successfully, awaiting payment',
                'application' => $application
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Collaboration request not found'
            ], 404);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return response()->json([
                'error' => 'Stripe API Error',
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create application',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/applications/{collaboration}",
     *     summary="Submit an application",
     *     tags={"Applications"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="collaboration",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="payment_intent_id", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Application submitted"),
     *     @OA\Response(response=403, description="Already applied or not allowed"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function store(Request $request, $collaborationId)
    {
        $collaboration = CollaborationRequest::findOrFail($collaborationId);
        
        // Validate no existing application
        if ($collaboration->applications()->where('user_id', Auth::id())->exists()) {
            return response()->json(['error' => 'Already applied'], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string',
            'payment_intent_id' => $collaboration->application_fee ? 'required|string' : 'nullable'
        ]);

        // If there's a fee, verify payment
        if ($collaboration->application_fee) {
            $intent = PaymentIntent::retrieve($validated['payment_intent_id']);
            if ($intent->status !== 'succeeded') {
                return response()->json(['intent' => $intent, 'error' => 'Payment required'], 403);
            }
        }

        $application = Application::create([
            'user_id' => Auth::id(),
            'collaboration_request_id' => $collaborationId,
            'message' => $validated['message'],
            'payment_intent_id' => $validated['payment_intent_id'] ?? null,
            'payment_status' => $collaboration->application_fee ? 'paid' : null,
            'status' => Application::STATUS_PENDING
        ]);

        return response()->json($application, 201);
    }

    /**
     * @OA\Put(
     *     path="/api/applications/{application}/status",
     *     summary="Update application status",
     *     tags={"Applications"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="application",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", enum={"accepted", "rejected"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Status updated"),
     *     @OA\Response(response=403, description="Not allowed"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function updateStatus(Request $request, $id)
    {
        $application = Application::findOrFail($id);
        
        // Only collaboration owner can update status
        if ($application->collaborationRequest->user_id !== Auth::id()) {
            return response()->json(['error' => 'Not authorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:accepted,rejected'
        ]);

        $application->update([
            'status' => $validated['status']
        ]);

        return response()->json($application);
    }

    /**
     * @OA\Post(
     *     path="/api/applications/{application}/withdraw",
     *     summary="Withdraw an application",
     *     tags={"Applications"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="application",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Application withdrawn"),
     *     @OA\Response(response=403, description="Not allowed"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function withdraw($id)
    {
        $application = Application::where('user_id', Auth::id())->findOrFail($id);
        
        if ($application->status !== Application::STATUS_PENDING) {
            return response()->json(['error' => 'Can only withdraw pending applications'], 403);
        }

        $application->update([
            'status' => Application::STATUS_WITHDRAWN
        ]);

        return response()->json($application);
    }
} 