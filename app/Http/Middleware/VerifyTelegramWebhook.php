<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTelegramWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.telegram.webhook_secret');
        $token = config('services.telegram.bot_token');
        if (! is_string($token) || trim($token) === '' || ! is_string($secret) || ! preg_match('/\A[A-Za-z0-9_-]{1,256}\z/', $secret)) {
            return response()->json(['message' => 'Telegram webhook is not configured.'], 503);
        }

        $supplied = $request->header('X-Telegram-Bot-Api-Secret-Token');
        if (! is_string($supplied) || ! hash_equals($secret, $supplied)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
