<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PushSubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(PushSubscriptionRequest $request): JsonResponse
    {
        $request->user()->updatePushSubscription(
            $request->validated('endpoint'),
            $request->input('keys.p256dh'),
            $request->input('keys.auth'),
            $request->input('contentEncoding', 'aes128gcm'),
        );

        return response()->json(['subscribed' => true], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->deletePushSubscription((string) $request->input('endpoint'));

        return response()->json(['subscribed' => false]);
    }
}
