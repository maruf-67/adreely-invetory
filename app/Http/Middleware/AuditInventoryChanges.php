<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuditInventoryChanges
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Log inventory-related actions
        if ($this->isInventoryAction($request)) {
            Log::channel('inventory')->info('Inventory Action', [
                'user_id' => $request->user()?->id,
                'business_id' => $request->user()?->business_id,
                'action' => $request->method() . ' ' . $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'payload' => $request->except(['password', 'password_confirmation']),
                'timestamp' => now(),
            ]);
        }
        
        return $response;
    }
    
    private function isInventoryAction(Request $request): bool
    {
        $inventoryPaths = [
            'products',
            'purchase-orders',
            'sales-orders',
        ];
        
        foreach ($inventoryPaths as $path) {
            if (str_contains($request->path(), $path)) {
                return true;
            }
        }
        
        return false;
    }
}
