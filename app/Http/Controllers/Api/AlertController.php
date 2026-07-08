<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $alerts = $request->user()
            ->alertsReceived()
            ->with('fromUser:id,username,name')
            ->latest()
            ->limit(20)
            ->get();

        return response()->json($alerts);
    }

    public function unread(Request $request): JsonResponse
    {
        $alerts = $request->user()
            ->alertsReceived()
            ->whereNull('read_at')
            ->with('fromUser:id,username,name')
            ->latest()
            ->get();

        return response()->json($alerts);
    }

    public function markRead(Request $request, UserAlert $alert): JsonResponse
    {
        abort_unless($alert->to_user_id === $request->user()->id, 403);

        $alert->markAsRead();

        return response()->json($alert);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()
            ->alertsReceived()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Alertas marcados como lidos.']);
    }
}
