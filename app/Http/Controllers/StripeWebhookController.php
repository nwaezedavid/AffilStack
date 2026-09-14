<?php

namespace App\Http\Controllers;

use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function handle(Request $request, PaymentGatewayManager $gateways, PaymentProcessor $processor): JsonResponse
    {
        $gateway = $gateways->get('stripe');

        if (! $gateway->verifyWebhookSignature($request)) {
            abort(401, 'Invalid webhook signature.');
        }

        $result = $gateway->resolveFromWebhook($request);

        if ($result) {
            $processor->process('stripe', $result);
        }

        return response()->json(['status' => 'ok']);
    }
}
