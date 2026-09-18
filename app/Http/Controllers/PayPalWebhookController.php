<?php

namespace App\Http\Controllers;

use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentProcessor;
use App\Services\Payments\PayPalGateway;
use App\Services\Payments\RefundProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayPalWebhookController extends Controller
{
    public function handle(
        Request $request,
        PaymentGatewayManager $gateways,
        PaymentProcessor $processor,
        RefundProcessor $refunds,
    ): JsonResponse {
        $gateway = $gateways->get('paypal');

        if (! $gateway->verifyWebhookSignature($request)) {
            abort(401, 'Invalid webhook signature.');
        }

        $result = $gateway->resolveFromWebhook($request);

        if ($result) {
            $processor->process('paypal', $result);
        }

        if ($gateway instanceof PayPalGateway && $refundEvent = $gateway->resolveRefundEvent($request)) {
            $refunds->process('paypal', $refundEvent);
        }

        return response()->json(['status' => 'ok']);
    }
}
