<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockRequest;
use Illuminate\Http\Request;
use App\Models\Stock;
use Illuminate\Support\Facades\DB; // agregado
use Exception;

class StockController extends Controller
{

    public function index()
    {
        return Stock::all();
    }


    public function store(StoreStockRequest $request)
    {
        $datosValidos = $request->validated();
        $stock = Stock::create($datosValidos);
        $stock->save();
        return ($stock);
    }


    public function show(string $id)
    {
        return Stock::find($id);
    }

    public function update(Request $request, string $id)
    {
        //
    }


    public function destroy(string $id)
    {
        //
    }

    /**
     * Devuelve la trazabilidad / historial de movimientos de un producto en una sucursal
     * Request: id_producto (int), id_sucursal (int)
     *
     * Reglas:
     * - Para tipos 'Compra' o 'Venta' incluir campo 'referencia' con id_compra o id_venta correspondiente.
     * - Para 'Manual' solo mostrar filas con observaciones no vacías.
     * - Para 'Transferencia' devolver observaciones tal como están (y id_transferencia si existe).
     */
    public function trazabilidad(Request $request)
    {
        $request->validate([
            'id_producto' => 'required|integer|exists:producto,id_producto',
            'id_sucursal' => 'required|integer|exists:sucursal,id_sucursal',
        ]);

        try {
            $idProducto = $request->id_producto;
            $idSucursal = $request->id_sucursal;

            $rows = DB::table('stock')
                ->where('id_producto', $idProducto)
                ->where('id_sucursal', $idSucursal)
                ->orderByDesc('fecha')
                ->get();

            $historial = $rows->map(function ($r) {
                // fecha: usar created_at si existe
                $fecha = $r->fecha ?? ($r->fecha ?? null);

                $tipo = $r->tipo ?? null;
                $observaciones = isset($r->observaciones) ? $r->observaciones : null;

                // Referencia para compras/ventas (si la tabla tiene esas columnas)

                if (!empty($tipo)) {
                    $lower = mb_strtolower($tipo);
                    if (str_contains($lower, 'compra') && isset($r->id_compra)) {
                        $observaciones = 'ID de Compra: '.$r->id_compra;
                    } elseif (str_contains($lower, 'venta') && isset($r->id_venta)) {
                        $observaciones = 'ID de Venta: '.$r->id_venta;
                    }
                }

                // Para manual: solo devolver si hay observaciones
                if ($tipo && mb_strtolower($tipo) === 'manual' && (empty($observaciones) || trim($observaciones) === '')) {
                    return null; // será filtrado luego
                }

                // Formatear fecha y hora
                $fechaFormato = null;
                $horaFormato = null;
                
                if ($fecha) {
                    $fechaObj = \Carbon\Carbon::parse($fecha);
                    $fechaFormato = $fechaObj->format('d/m/Y');
                    $horaFormato = $fechaObj->format('H:i');
                }

                return (object)[
                    'fecha' => $fechaFormato,
                    'hora' => $horaFormato,
                    'cantidad' => $r->cantidad,
                    'tipo' => $tipo,
                    'observaciones' => $observaciones,
                ];
            })->filter()->values();

            return response()->json($historial, 200);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error al obtener trazabilidad.',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

}
