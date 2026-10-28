<?php

namespace App\Http\Controllers;

use App\Domain\Payments\RazorpayWebhooks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, RazorpayWebhooks $hooks): JsonResponse
    {
        if (strlen($request->getContent()) > 256 * 1024) {
            return response()->json(['result' => 'too_large'], 413);
        }
        [$status, $result] = $hooks->handle($request->getContent(), (string) $request->header('X-Razorpay-Signature', ''), $request->header('X-Razorpay-Event-Id'));

        return response()->json(['result' => $result], $status);
    }
}
