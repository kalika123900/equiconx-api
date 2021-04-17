<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Tymon\JWTAuth\JWTAuth;

class Jwt
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function handle($request, Closure $next)
    {
        try {
            $user = JWTAuth::toUser($request->input('token'));
        } catch (Exception $e) {
            if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException){
                return $next($request);
                return response()->json(['error'=>'Token is Invalid']);
            }else if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException){
                return $next($request);
                return response()->json(['error'=>'Token is Expired']);
            }else{
                return $next($request);
                return response()->json(['error'=>'Something is wrong']);
            }
        }
        return $next($request);
    }
}
