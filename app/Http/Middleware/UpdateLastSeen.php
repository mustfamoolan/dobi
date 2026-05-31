<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastSeen
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();
            
            // Update last_seen if it is null or was updated more than 1 minute ago
            if (!$user->last_seen || now()->diffInMinutes($user->last_seen) >= 1) {
                // Disable auto-timestamps update to keep updated_at clean
                $user->timestamps = false;
                $user->last_seen = now();
                $user->save();
            }
        }

        return $next($request);
    }
}
