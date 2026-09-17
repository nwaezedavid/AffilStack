<?php

namespace App\Http\Controllers;

use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentProcessor;
use App\Services\Payments\RefundProcessor;
use App\Services\Payments\StripeGateway;
use App\Services\Payments\SubscriptionRenewalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function handle(
        Request $request,
        PaymentGatewayManager $gateways,
        PaymentProcessor $processor,
        SubscriptionRenewalService $renewals,
        RefundProcessor $refunds,
    ): JsonResponse {
        $gateway = $gateways->get('stripe');

        if (! $gateway->verifyWebhookSignature($request)) {
            abort(401, 'Invalid webhook signature.');
        }

        $result = $gateway->resolveFromWebhook($request);

        if ($result) {
            $processor->process('stripe', $result);
        }

        // Renewal-cycle events (task #85) — see StripeGateway::resolveRenewalEvent().
        if ($gateway instanceof StripeGateway && $renewalEvent = $gateway->resolveRenewalEvent($request)) {
            $renewals->handleStripeEvent($renewalEvent);
        }

        // Refunds/chargebacks (audit gap #6) — see StripeGateway::resolveRefundEvent().
        if ($gateway instanceof StripeGateway && $refundEvent = $gateway->resolveRefundEvent($request)) {
            $refunds->process('stripe', $refundEvent);
        }

        return response()->json(['status' => 'ok']);
    }
}
