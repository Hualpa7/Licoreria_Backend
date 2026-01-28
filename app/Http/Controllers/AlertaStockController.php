<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AlertaStock;
use Tymon\JWTAuth\Facades\JWTAuth;

class AlertaStockController extends Controller
{
   
    public function obtenerAlertasSucursal(Request $request)
    {
        try {
            // Autenticar usuario desde el token
            $usuario = JWTAuth::parseToken()->authenticate();

            // Determinar la sucursal según el rol
            if ($usuario->id_rol != config('roles.superadmin')) {
                // Es vendedor/admin: usa su sucursal
                $idSucursal = $usuario->id_sucursal;
            } else {
                // Es superAdmin: requiere id_sucursal en el request
                $request->validate([
                    'id_sucursal' => 'required|integer|exists:sucursal,id_sucursal'
                ]);
                $idSucursal = $request->id_sucursal;
            }

            // Obtener alertas de stock de la sucursal
            $alertas = AlertaStock::where('id_sucursal', $idSucursal)
                ->orderBy('fecha_alerta', 'desc')
                ->get();

            return response()->json($alertas, 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener alertas',
                'detalle' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Obtiene el contador de alertas para mostrar en el badge
     */
    public function contarAlertasSucursal(Request $request)
    {
        try {
            $usuario = JWTAuth::parseToken()->authenticate();

            if ($usuario->id_rol != config('roles.superadmin')) {
                $idSucursal = $usuario->id_sucursal;
            } else {
                $request->validate([
                    'id_sucursal' => 'required|integer|exists:sucursal,id_sucursal'
                ]);
                $idSucursal = $request->id_sucursal;
            }

            $cantidad = AlertaStock::where('id_sucursal', $idSucursal)
                ->count();

            return response()->json([
                'cantidad' => $cantidad
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al contar alertas',
                'detalle' => $e->getMessage()
            ], 400);
        }
    }
    
    //  Obtiene todas las alertas (solo para SuperAdmin)
     
    public function obtenerTodasLasAlertas()
    {
        try {
            $usuario = JWTAuth::parseToken()->authenticate();

            // Verificar que sea superAdmin
            if ($usuario->id_rol != config('roles.superadmin')) {
                return response()->json([
                    'error' => 'No tienes permiso para ver todas las alertas'
                ], 403);
            }

            $alertas = AlertaStock::with('sucursalRelacion')
                ->orderBy('fecha_alerta', 'desc')
                ->get();

            return response()->json($alertas, 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener alertas',
                'detalle' => $e->getMessage()
            ], 400);
        }
    }
}