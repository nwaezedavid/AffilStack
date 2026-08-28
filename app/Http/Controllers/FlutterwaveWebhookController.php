<?php

namespace App\Http\Controllers;

use App\Services\Flutterwave\FlutterwaveClient;
use App\Services\Flutterwave\PaymentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlutterwaveWebhookController extends Controller
{
    public function handle(Request $request, FlutterwaveClient $flutterwave, PaymentProcessor $processor): JsonResponse
    {
        if (! $flutterwave->verifyWebhookSignature($request->header('verif-hash'))) {
            abort(401, 'Invalid webhook signature.');
        }

        $payload = $request->input('data', []);

        // Trust the signed webhook only to tell us *which* transaction to look
        // at — always re-verify the actual amount/status server-to-server
        // rather than trusting the numbers in the webhook body directly.
        if (! empty($payload['id'])) {
            $verified = $flutterwave->verifyTransaction((string) $payload['id']);
            $processor->process($verified);
        }

        return response()->json(['status' => 'ok']);
    }
}
