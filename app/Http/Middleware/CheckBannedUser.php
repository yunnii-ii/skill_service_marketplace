<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckBannedUser
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if(Auth::check() && Auth::user()->is_banned){
            Auth::user()->currentAccessToken()->delete();
            return response()->json([
                'message' => 'Your account has banned by the administrator.'
            ], 403);
        }
        return $next($request);
    }
}
