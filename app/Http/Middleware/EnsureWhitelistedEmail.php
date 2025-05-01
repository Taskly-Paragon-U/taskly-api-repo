<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWhitelistedEmail
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user  = $request->user();
        $email = $user->email;
    
        if (! \App\Models\WhitelistedEmail::where('email', $email)->exists()) {
            // not in the school list
            return response()->json([
                'message' => 'Only school accounts may create contracts.'
            ], 403);
        }
    
        return $next($request);
    }
    
}
