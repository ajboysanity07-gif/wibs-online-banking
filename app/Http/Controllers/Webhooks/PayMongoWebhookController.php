<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payments\PayMongoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayMongoWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        PayMongoService $payMongo,
    ): JsonResponse {
        $rawPayload = $request->getContent();
        $signature = $request->headers->get('Paymongo-Signature');

        if (! $payMongo->verifyWebhookSignature($rawPayload, $signature)) {
            return response()->json([
                'message' => 'Invalid PayMongo signature.',
            ], 401);
        }

        $payload = json_decode($rawPayload, true);

        if (! is_array($payload)) {
            return response()->json([
                'message' => 'Invalid JSON payload.',
            ], 400);
        }

        $payMongo->handleWebhook($payload);

        return response()->json([
            'message' => 'SUCCESS',
        ]);
    }
}
