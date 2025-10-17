<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;

class CheckPermisos //chequea permisos generricos
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $permiso)
    {
        try {
            $usuario = JWTAuth::parseToken()->authenticate();

            if (!$usuario) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            if (!$usuario->tienePermiso($permiso)) {
                return response()->json(['error' => 'No autorizado'], 403);
            }

            return $next($request);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Token no valido'], 401);
        }
    }
}
