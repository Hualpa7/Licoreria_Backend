<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreVentaRequest;
use Illuminate\Http\Request;
use App\Models\Venta;
use Carbon\Carbon; //Esto usado en el filtro de fechas
use Tymon\JWTAuth\Facades\JWTAuth;
use function Laravel\Prompts\table;

class VentaController extends Controller
{

    public function index()
    {
        $venta = Venta::with(['usuario'])->get();
        return $venta;
    }

    /*
    
    public function store(StoreVentaRequest $request)
    {

        $datosValidos = $request->validated();

        DB::transaction(function () use ($datosValidos, $request) { //envuelvo todo en una transaccion para que se carguen loso registros
            $venta = Venta::create($datosValidos);                 //de manera simultanea y si ocurre un error no se cargue el de venta antes 
            $productosVenta = [];                                //que el de venta_productos

            foreach ($request->productos as $item) {
                if ($item['esCombo']) {
                    $combo = DB::table('combo')
                        ->where('combo.id_combo', $item['id_combo'])
                        ->leftJoin('combo_producto', 'combo.id_combo', '=', 'combo_producto.id_combo')
                        ->leftJoin('producto', 'combo_producto.id_producto', '=', 'producto.id_producto')
                        ->select(

                            DB::raw('json_agg(json_build_object(
                    \'producto\',producto.producto,
                    \'id_producto\',producto.id_producto,
                    \'cantidad\',combo_producto.cantidad
                    )) as productos')
                        )
                        ->groupBy('combo.id_combo')
                        ->first();
                    $combo->productos = json_decode($combo->productos);

                    foreach ($combo->productos as $producto) {
                        $id = $producto->id_producto;
                        $cantidad = $producto->cantidad * $item['Cantidad'];

                        if (!isset($productosVenta[$id])) {
                            $productosVenta[$id] = [
                                'id_producto' => $id,
                                'cantidad' => 0,
                                'iva' => 0
                            ];
                        }
                        $productosVenta[$id]['cantidad'] += $cantidad;
                    }
                } else {
                    $id = $item['id_producto'];
                    $cantidad = $item['Cantidad'];
                    $iva = $item['IVA'];

                    if (!isset($productosVenta[$id])) {
                        $productosVenta[$id] = [
                            'id_producto' => $id,
                            'cantidad' => 0,
                            'iva' => $iva
                        ];
                    }
                    $productosVenta[$id]['cantidad'] += $cantidad;
                }
            }
            // Verificamos stock y hacemos los inserts finales
    foreach ($productosVenta as $p) {
        $stock = DB::table('stock')->where('id_producto', $p['id_producto'])->sum('cantidad');

        if ($p['cantidad'] > $stock) {
            throw new \Exception("Error. No hay stock suficiente del producto ID {$p['id_producto']}.");
        }

        DB::table('venta_producto')->insert([
            'id_venta' => $venta->id_venta,
            'id_producto' => $p['id_producto'],
            'cantidad' => $p['cantidad'],
            'iva' => $p['iva']
        ]);

        DB::table('stock')->insert([
            'cantidad' => -$p['cantidad'],
            'tipo' => "Venta",
            'id_producto' => $p['id_producto'],
            'id_venta' => $venta->id_venta,
            'id_sucursal' => $venta->id_sucursal
        ]);
    }

    $venta->save();
            return response()->json(['message' => 'Venta realizada exitosamente'], 201);
        });
    }
    */



    public function store(StoreVentaRequest $request)
    {
        $datosValidos = $request->validated();

        try {

            // Autenticar usuario desde el token
            $usuario = JWTAuth::parseToken()->authenticate();
            $idUsuario = $usuario->id_usuario;

            // Determinar sucursal según el rol
            if ($usuario->id_rol != 5) {
                $idSucursal = $usuario->id_sucursal;
            } else {
                $request->validate([
                    'id_sucursal' => 'required|exists:sucursal,id_sucursal'
                ]);
                $idSucursal = $request->id_sucursal;
            }

            DB::transaction(function () use ($datosValidos, $request, $idSucursal, $idUsuario) {
                $venta = Venta::create(array_merge($datosValidos, ['id_sucursal' => $idSucursal, 'id_usuario' => $idUsuario]));

                foreach ($request->productos as $item) {
                    if ($item['esCombo']) {
                        $combo = DB::table('combo')
                            ->where('combo.id_combo', $item['id_combo'])
                            ->leftJoin('combo_producto', 'combo.id_combo', '=', 'combo_producto.id_combo')
                            ->leftJoin('producto', 'combo_producto.id_producto', '=', 'producto.id_producto')
                            ->select(DB::raw('json_agg(json_build_object(
                            \'producto\', producto.producto,
                            \'id_producto\', producto.id_producto,
                            \'cantidad\', combo_producto.cantidad
                        )) as productos'))
                            ->groupBy('combo.id_combo')
                            ->first();

                        $combo->productos = json_decode($combo->productos);

                        DB::table('venta_combo')->insert([
                            'id_venta' => $venta->id_venta,
                            'id_combo' => $item['id_combo'],
                            'cantidad' => $item['Cantidad'],
                        ]);

                        foreach ($combo->productos as $producto) {
                            $stock1 = DB::table('stock')
                                ->where('id_producto', $producto->id_producto)
                                ->where('id_sucursal', $idSucursal)
                                ->sum('cantidad');

                            if ($producto->cantidad * $item['Cantidad'] <= $stock1) {
                                DB::table('stock')->insert([
                                    'cantidad' => -$producto->cantidad * $item['Cantidad'],
                                    'tipo' => "Venta",
                                    'id_producto' => $producto->id_producto,
                                    'id_venta' => $venta->id_venta,
                                    'id_sucursal' => $idSucursal
                                ]);
                            } else {
                                // Lanzamos excepción con detalle
                                throw new \Exception("No hay stock suficiente del producto '{$producto->producto}' dentro del combo.");
                            }
                        }
                    } else {
                        $stock = DB::table('stock')
                            ->where('id_producto', $item['id_producto'])
                            ->where('id_sucursal', $idSucursal)
                            ->sum('cantidad');

                        if ($item['Cantidad'] <= $stock) {
                            DB::table('venta_producto')->insert([
                                'id_venta' => $venta->id_venta,
                                'id_producto' => $item['id_producto'],
                                'cantidad' => $item['Cantidad'],
                                'iva' => $item['IVA']
                            ]);

                            DB::table('stock')->insert([
                                'cantidad' => -$item['Cantidad'],
                                'tipo' => "Venta",
                                'id_producto' => $item['id_producto'],
                                'id_venta' => $venta->id_venta,
                                'id_sucursal' => $idSucursal
                            ]);
                        } else {
                            throw new \Exception("No hay stock suficiente del producto '{$item['Nombre']}'.");
                        }
                    }
                }

                $venta->save();
            });

            return response()->json(['message' => 'Venta realizada exitosamente'], 201);
        } catch (\Exception $e) {
            // Capturamos cualquier error dentro de la transacción
            return response()->json([
                'error' => 'Error al realizar la venta',
                'detalle' => $e->getMessage(), // <-- mensaje personalizado
            ], 400);
        }
    }


    /*public function show(string $id)
    {

        $venta = DB::table('venta')
            ->where('venta.id_venta', $id)
            ->leftJoin('venta_producto', 'venta.id_venta', '=', 'venta_producto.id_venta')
            ->leftJoin('venta_combo', 'venta.id_venta', '=', 'venta_combo.id_venta')
            ->leftJoin('combo', 'venta_combo.id_combo', '=', 'combo.id_combo')
            ->leftJoin('producto', 'venta_producto.id_producto', '=', 'producto.id_producto')
            ->leftJoin('descuento', 'producto.id_descuento', '=', 'descuento.id_descuento')
            ->select(
                'venta.id_venta',
                DB::raw('json_agg(json_build_object(
                 \'codigo\', producto.codigo,
                 \'codigocombo\', combo.codigo,
                 \'producto\',producto.producto,
                 \'costo\',TO_CHAR(producto.costo, \'FM999999999.00\'),
                 \'cantidad\',venta_producto.cantidad,
                 \'iva\',venta_producto.iva,
                 \'descuento_porcentaje\',descuento.porcentaje
                  )) as productos')
            )
            ->groupBy('venta.id_venta')
            ->first();

        if ($venta)
            $venta->productos = json_decode($venta->productos);
        else return response()->json(['error' => 'Venta no encontrada'], 404);

        return response()->json($venta);
    }
*/

    public function show(string $id)
    {
        try {
            $venta = DB::table('venta')
                ->select(
                    'venta.id_venta',
                    DB::raw("
                    (
                        COALESCE(
                            (
                                SELECT json_agg(json_build_object(
                                    'tipo', 'producto',
                                    'codigo', p.codigo,
                                    'nombre', p.producto,
                                    'cantidad', vp.cantidad,
                                    'iva', vp.iva,
                                    'costo', TO_CHAR(p.costo, 'FM999999999.00'),
                                    'descuento_porcentaje', d.porcentaje
                                ))::jsonb
                                FROM venta_producto vp
                                LEFT JOIN producto p ON vp.id_producto = p.id_producto
                                LEFT JOIN descuento d ON p.id_descuento = d.id_descuento
                                WHERE vp.id_venta = venta.id_venta
                            ),
                            '[]'::jsonb
                        )
                        ||
                        COALESCE(
                            (
                                SELECT json_agg(json_build_object(
                                    'tipo', 'combo',
                                    'codigo', c.codigo, -- o c.codigo si existe
                                    'nombre', c.nombre,
                                    'cantidad', vc.cantidad,
                                    'iva', 0,
                                    'costo', TO_CHAR(c.costo, 'FM999999999.00'),
                                    'descuento_porcentaje', NULL
                                ))::jsonb
                                FROM venta_combo vc
                                LEFT JOIN combo c ON vc.id_combo = c.id_combo
                                WHERE vc.id_venta = venta.id_venta
                            ),
                            '[]'::jsonb
                        )
                    ) AS productos_json
                ")
                )
                ->where('venta.id_venta', $id)
                ->first();

            if (!$venta) {
                return response()->json(['error' => 'Venta no encontrada'], 404);
            }

            // Decodificar el JSON combinado
            $venta->productos = json_decode($venta->productos_json);
            unset($venta->productos_json);

            return response()->json($venta, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener la venta',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }


    public function update(Request $request, string $id)
    {
        //
    }


    public function destroy(string $id)
    {
        //
    }

    public function filtro(Request $request)
    {
        try {

            //Autenticar usuario desde el token
            $usuario = JWTAuth::parseToken()->authenticate();

            // Si NO es superadmin (id_rol <> 5), usa la sucursal del token
            // Si es superadmin, valida que haya una sucursal recibida en el request
            if ($usuario->id_rol != 5) {
                $idSucursal = $usuario->id_sucursal;
            } else {
                $request->validate([
                    'id_sucursal' => 'required|exists:sucursal,id_sucursal'
                ]);
                $idSucursal = $request->id_sucursal;
            }


            $where = Venta::with('usuario')
                ->where('id_sucursal', $idSucursal); //filtro por sucursal obligatoriamente


            if ($request->metodo_pago != null)
                $where = $where->where('metodo_pago', strtolower($request->metodo_pago));

            if ($request->periodo_ventas != null && ($request->periodo_ventas === "Ver ventas desde el") && (
                $request->fecha_desde != null && $request->fecha_hasta != null)) {
                $fecha_desde = Carbon::parse($request->fecha_desde)->startOfDay(); //toma la fecha desde el comienzo del dia
                $fecha_hasta = Carbon::parse($request->fecha_hasta)->endOfDay(); //toma la fecha hasta el final del dia
                $where = $where->whereBetween('fecha', [$fecha_desde, $fecha_hasta]);
            }

            if ($request->periodo_ventas != null && ($request->periodo_ventas === "Ventas del mes de") && (
                $request->mes_venta != null && $request->anio_venta != null)) {
                $mes = $request->mes_venta;
                $anio = $request->anio_venta;

                $where = $where->whereYear('fecha', $anio)->whereMonth('fecha', $mes);
            }




            return $where->get();
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al filtrar ventas.',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }




    public function obtenerAnios()
    {
        $anios = Venta::selectRaw('EXTRACT(YEAR FROM fecha) as anio')
            ->distinct()
            ->orderBy('anio')
            ->pluck('anio');

        return response()->json($anios);
    }

    
     // Obtiene la cantidad total de ventas
     
    public function cantidadTotalVentas()
    {
        try {
            $cantidadTotalVentas = Venta::count();

            return response()->json($cantidadTotalVentas, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener la cantidad total de ventas',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }
}
