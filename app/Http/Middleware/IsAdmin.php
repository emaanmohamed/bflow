<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (Auth::check()) {
            if (Auth::user()->is_admin === 1) {
                return $next($request);
            } else {
                return \Redirect::Route('dashboard')->with([
                    'status'    => 'Only admins can access this page',
                    'statusType'      => "danger"
                ]);
            }
        }

        return redirect()->route('get_login');
    }
}
