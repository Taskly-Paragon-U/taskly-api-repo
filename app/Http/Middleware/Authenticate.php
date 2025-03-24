<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Return 401 JSON if unauthenticated, no redirect.
     */
    protected function redirectTo(Request $request): ?string
    {
        if (! $request->expectsJson()) {
            abort(401, 'Unauthorized');
        }
        return null;
    }
}
