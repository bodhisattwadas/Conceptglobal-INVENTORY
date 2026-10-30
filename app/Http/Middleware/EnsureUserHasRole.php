<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userRole = $request->user()?->role instanceof \BackedEnum
            ? $request->user()->role->value
            : (string) $request->user()?->role;

        abort_unless($request->user() && in_array($userRole, $roles, true), 403);

        return $next($request);
    }
}
