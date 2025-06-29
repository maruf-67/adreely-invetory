<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToBusiness
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Check if user belongs to a business
        if (!$user || !$user->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'User must belong to a business to access this resource'
            ], 403);
        }

        // Check if the business is active
        if ($user->business && !$user->business->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Business account is inactive'
            ], 403);
        }

        return $next($request);
    }
}
