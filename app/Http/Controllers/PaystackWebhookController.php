<?php

namespace App\Http\Controllers;

use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentProcessor;
use App\Services\Payments\PaystackGateway;
use App\Services\Payments\RefundProcessor;
use App\Services\Referrals\PayoutDisbursementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaystackWebhookController extends Controller
{
    public function handle(
        Request $request,
        PaymentGatewayManager $gateways,
        PaymentProcessor $processor,
        RefundProcessor $refunds,
        PayoutDisbursementService $payouts,
    ): JsonResponse {
        $gateway = $gateways->get('paystack');

        if (! $gateway->verifyWebhookSignature($request)) {
            abort(401, 'Invalid webhook signature.');
        }

        // Partner payouts sent with Paystack Transfers finish here.
        $event = (string) $request->input('event');

        if (str_starts_with($event, 'transfer.')) {
            $payouts->handlePaystackTransferEvent($event, (array) $request->input('data', []));

            return response()->json(['status' => 'ok']);
        }

        $result = $gateway->resolveFromWebhook($request);

        if ($result) {
            $processor->process('paystack', $result);
        }

        if ($gateway instanceof PaystackGateway && $refundEvent = $gateway->resolveRefundEvent($request)) {
            $refunds->process('paystack', $refundEvent);
        }

        return response()->json(['status' => 'ok']);
    }
}
