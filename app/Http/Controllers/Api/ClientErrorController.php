<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ClientErrorController - Receive and log client-side JavaScript errors
 *
 * FE-005: Endpoint for ErrorBoundary to report caught errors
 */
class ClientErrorController extends Controller
{
    /**
     * Log a client-side error.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => 'required|string|max:50',
            'message' => 'required|string|max:1000',
            'stack' => 'nullable|string|max:10000',
            'component_stack' => 'nullable|string|max:10000',
            'url' => 'required|string|max:2000',
            'user_agent' => 'nullable|string|max:500',
            'timestamp' => 'required|string',
        ]);

        // Get user context if authenticated
        $userId = auth()->id();
        $userEmail = auth()->user()?->email;

        // Log the error
        Log::channel('client_errors')->error('Client-side error', [
            'event_id' => $validated['event_id'],
            'message' => $validated['message'],
            'stack' => $validated['stack'] ?? null,
            'component_stack' => $validated['component_stack'] ?? null,
            'url' => $validated['url'],
            'user_agent' => $validated['user_agent'] ?? null,
            'timestamp' => $validated['timestamp'],
            'user_id' => $userId,
            'user_email' => $userEmail,
            'ip' => $request->ip(),
        ]);

        // In production, you might want to send to an error tracking service
        // like Sentry, Bugsnag, or Rollbar here

        return response()->json([
            'status' => 'logged',
            'event_id' => $validated['event_id'],
        ], 201);
    }
}
