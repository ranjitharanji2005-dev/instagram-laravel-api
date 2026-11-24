<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected function redirectTo($request): ?string
    {
        // API requests should return an authentication error,
        // not redirect to a web login page.
        if ($request->is('api/*')) {
            return null;
        }

        // Keep the existing behavior for web requests.
        if (! $request->expectsJson()) {
            return route('login');
        }

        return null;
    }
}