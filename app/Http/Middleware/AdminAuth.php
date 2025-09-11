<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Admin;

class AdminAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role = null): Response
    {
        // Check if user is authenticated via Sanctum
        if (!$request->user()) {
            return response()->json([
                'ok' => false,
                'error' => 'Unauthenticated',
            ], 401);
        }

        // Check if the authenticated user is an Admin
        if (!($request->user() instanceof Admin)) {
            return response()->json([
                'ok' => false,
                'error' => 'Admin access required',
            ], 403);
        }

        $admin = $request->user();

        // Check if admin is active
        if (!$admin->isActive()) {
            return response()->json([
                'ok' => false,
                'error' => 'Admin account is deactivated',
            ], 403);
        }

        // Check role if specified
        if ($role && $admin->role !== $role) {
            return response()->json([
                'ok' => false,
                'error' => "Access denied. {$role} role required.",
            ], 403);
        }

        return $next($request);
    }
}
