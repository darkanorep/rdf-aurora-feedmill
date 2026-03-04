<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    public function handle($request, Closure $next, ...$guards)
    {
        if ($token = $request->cookie('sanctum')) {
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        $this->authenticate($request, $guards);

        return $next($request);
    }

    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('login');
    }
}