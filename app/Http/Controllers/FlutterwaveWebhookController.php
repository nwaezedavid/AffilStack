<?php

namespace App\Http\Controllers;

use App\Services\Payments\FlutterwaveGateway;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentProcessor;
use App\Services\Payments\RefundProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlutterwaveWebhookController extends Controller
{
    public function handle(
        Request $request,
        PaymentGatewayManager $gateways,
        PaymentProcessor $processor,
        RefundProcessor $refunds,
    ): JsonResponse {
        $gateway = $gateways->get('flutterwave');

        if (! $gateway->verifyWebhookSignature($request)) {
            abort(401, 'Invalid webhook signature.');
        }

        $result = $gateway->resolveFromWebhook($request);

        if ($result) {
            $processor->process('flutterwave', $result);
        }

        // Refunds/chargebacks (audit gap #6) — see FlutterwaveGateway::resolveRefundEvent().
        if ($gateway instanceof FlutterwaveGateway && $refundEvent = $gateway->resolveRefundEvent($request)) {
            $refunds->process('flutterwave', $refundEvent);
        }

        return response()->json(['status' => 'ok']);
    }
}
