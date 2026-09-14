<?php

namespace App\Http\Controllers;

use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlutterwaveWebhookController extends Controller
{
    public function handle(Request $request, PaymentGatewayManager $gateways, PaymentProcessor $processor): JsonResponse
    {
        $gateway = $gateways->get('flutterwave');

        if (! $gateway->verifyWebhookSignature($request)) {
            abort(401, 'Invalid webhook signature.');
        }

        $result = $gateway->resolveFromWebhook($request);

        if ($result) {
            $processor->process('flutterwave', $result);
        }

        return response()->json(['status' => 'ok']);
    }
}
