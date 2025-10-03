<?php

namespace App\Http\Controllers;

use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                config('services.stripe.webhook.secret')
            );

            Log::info('Stripe webhook received', ['type' => $event->type]);

            switch ($event->type) {
                case 'account.updated':
                    $this->stripeService->handleAccountUpdated($event->toArray());
                    break;

                case 'payment_intent.succeeded':
                    $this->stripeService->handlePaymentIntentSucceeded($event->toArray());
                    break;

                case 'payment_intent.payment_failed':
                    $this->stripeService->handlePaymentIntentFailed($event->toArray());
                    break;

                case 'transfer.failed':
                    $this->stripeService->handleTransferFailed($event->toArray());
                    break;
            }

            return response()->json(['status' => 'success']);
        } catch (SignatureVerificationException $e) {
            Log::error('Invalid Stripe webhook signature', [
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\Exception $e) {
            Log::error('Error processing Stripe webhook', [
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Webhook error'], 500);
        }
    }
} 