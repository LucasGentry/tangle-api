<?php

namespace App\Services;

use App\Models\User;
use App\Models\StripeTransaction;
use Illuminate\Support\Facades\Log;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Transfer;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createConnectAccount(User $user): Account
    {
        try {
            $account = Account::create([
                'type' => 'standard',
                'email' => $user->email,
                'metadata' => [
                    'user_id' => $user->id
                ]
            ]);

            $user->update([
                'stripe_account_id' => $account->id,
                'stripe_account_status' => 'pending'
            ]);

            return $account;
        } catch (\Exception $e) {
            Log::error('Failed to create Stripe Connect account', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function createAccountLink(User $user, string $refreshUrl, string $returnUrl): string
    {
        try {
            $accountLink = AccountLink::create([
                'account' => $user->stripe_account_id,
                'refresh_url' => $refreshUrl,
                'return_url' => $returnUrl,
                'type' => 'account_onboarding'
            ]);

            return $accountLink->url;
        } catch (\Exception $e) {
            Log::error('Failed to create Stripe account link', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function createPaymentIntent(array $data): PaymentIntent
    {
        try {
            return PaymentIntent::create([
                'amount' => $data['amount'] * 100, // Convert to cents
                'currency' => $data['currency'] ?? 'usd',
                'metadata' => $data['metadata'] ?? [],
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create payment intent', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function createTransfer(User $recipient, int $amount, array $metadata = []): Transfer
    {
        try {
            $transfer = Transfer::create([
                'amount' => $amount,
                'currency' => 'usd',
                'destination' => $recipient->stripe_account_id,
                'metadata' => array_merge([
                    'user_id' => $recipient->id
                ], $metadata)
            ]);

            StripeTransaction::create([
                'user_id' => $recipient->id,
                'stripe_transfer_id' => $transfer->id,
                'amount' => $amount / 100,
                'platform_fee' => 0,
                'currency' => 'usd',
                'status' => $transfer->status,
                'type' => 'transfer',
                'metadata' => $metadata
            ]);

            return $transfer;
        } catch (\Exception $e) {
            Log::error('Failed to create transfer', [
                'recipient_id' => $recipient->id,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function attachPaymentMethodToCustomer($user, $paymentMethodId)
    {
        // Create Stripe customer if not exists
        if (!$user->stripe_account_id) {
            $customer = \Stripe\Customer::create([
                'email' => $user->email,
            ]);
            $user->stripe_account_id = $customer->id;
            $user->save();
        }

        // Attach payment method to customer
        $stripePaymentMethod = \Stripe\PaymentMethod::retrieve($paymentMethodId);
        $stripePaymentMethod->attach(['customer' => $user->stripe_account_id]);

        return $stripePaymentMethod;
    }

    public function handleAccountUpdated(array $event): void
    {
        $account = $event['data']['object'];
        $user = User::where('stripe_account_id', $account['id'])->first();

        if (!$user) {
            Log::error('User not found for Stripe account', ['account_id' => $account['id']]);
            return;
        }

        $user->update([
            'charges_enabled' => $account['charges_enabled'],
            'payouts_enabled' => $account['payouts_enabled'],
            'stripe_account_status' => $account['charges_enabled'] && $account['payouts_enabled'] ? 'active' : 'pending',
            'stripe_account_details' => $account
        ]);

        if ($account['charges_enabled'] && $account['payouts_enabled']) {
            $user->update(['stripe_onboarding_completed_at' => now()]);
        }
    }

    public function handlePaymentIntentSucceeded(array $event): void
    {
        $paymentIntent = $event['data']['object'];
        
        $transaction = StripeTransaction::where('stripe_payment_intent_id', $paymentIntent['id'])->first();
        if ($transaction) {
            $transaction->update(['status' => 'succeeded']);
        }
    }

    public function handlePaymentIntentFailed(array $event): void
    {
        $paymentIntent = $event['data']['object'];
        
        $transaction = StripeTransaction::where('stripe_payment_intent_id', $paymentIntent['id'])->first();
        if ($transaction) {
            $transaction->update(['status' => 'failed']);
        }
    }

    public function handleTransferFailed(array $event): void
    {
        $transfer = $event['data']['object'];
        
        $transaction = StripeTransaction::where('stripe_transfer_id', $transfer['id'])->first();
        if ($transaction) {
            $transaction->update(['status' => 'failed']);
        }
    }
} 