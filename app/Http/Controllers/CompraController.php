<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreCompraRequest;

use Illuminate\Http\Request;
use App\Models\Compra;
use Carbon\Carbon; //Esto usado en el filtro de fechas
use Tymon\JWTAuth\Facades\JWTAuth;

class CompraController extends Controller
{

    public function index()
    {

        $compra = Compra::leftJoin('compra_producto', 'compra.id_compra', '=', 'compra_producto.id_compra')
            ->leftJoin('proveedor', 'compra.id_proveedor', '=', 'proveedor.id_proveedor')
            ->leftJoin('producto', 'compra_producto.id_producto', '=', 'producto.id_producto')
            ->select(
                'proveedor.nombre as nombreProveedor',
                'producto.producto as nombreProducto',
                'compra_producto.cantidad',
                'compra.fecha',
                'proveedor.correo',
                'proveedor.telefono',
                'compra.total'
            )
            ->get();


        return $compra;
    }


    public function store(StoreCompraRequest $request)
    {
        $datosValidos = $request->validated();
        try {

            // Autenticar usuario desde el token
            $usuario = JWTAuth::parseToken()->authenticate();

            // Determinar sucursal según el rol
            if ($usuario->id_rol != config('roles.superadmin')) {
                $idSucursal = $usuario->id_sucursal;
            } else {
                $request->validate([
                    'id_sucursal' => 'required|exists:sucursal,id_sucursal'
                ]);
                $idSucursal = $request->id_sucursal;
            }
            DB::transaction(function () use ($datosValidos, $request, $idSucursal) { //envuelvo todo en una transaccion para que se carguen los registros
                //de manera simultanea y si ocurre un error no se cargue el de compra antes 
                $compra = Compra::create(array_merge($datosValidos, ['id_sucursal' => $idSucursal]));
                //que el de compra_productos
                DB::table('compra_producto')->insert([
                    'id_compra' => $compra->id_compra,
                    'id_producto' => $request['id_producto'],
                    'cantidad' => $request['cantidad'],
                ]);

                DB::table('stock')->insert([
                    'cantidad' => $request['cantidad'],
                    'tipo' => "Compra",
                    'id_producto' => $request['id_producto'],
                    'id_compra' => $compra->id_compra,
                    'id_sucursal' => $idSucursal
                ]);

                $compra->save();
                return response()->json($compra, 201);
            });
        } catch (\Exception $e) {
            // Capturamos cualquier error dentro de la transacción
            return response()->json([
                'error' => 'Error al realizar la compra',
                'detalle' => $e->getMessage(), // <-- mensaje personalizado
            ], 400);
        }
    }



    public function show(string $id)
    {
        return Compra::find($id);
    }


    public function update(Request $request, string $id)
    {
        //
    }


    public function destroy(string $id)
    {
        //
    }

    public function obtenAños()
    {
        $años = DB::table('compra')
            ->select(DB::raw('DISTINCT EXTRACT(YEAR FROM fecha) as año'))
            ->pluck('año');

        return response()->json($años);
    }


    public function filtro(Request $request)
    {
        try {

            //Autenticar usuario desde el token
            $usuario = JWTAuth::parseToken()->authenticate();

            // Si NO es superadmin (id_rol <> 5), usa la sucursal del token
            // Si es superadmin, valida que haya una sucursal recibida en el request
            if ($usuario->id_rol != config('roles.superadmin')) {
                $idSucursal = $usuario->id_sucursal;
            } else {
                $request->validate([
                    'id_sucursal' => 'required|exists:sucursal,id_sucursal'
                ]);
                $idSucursal = $request->id_sucursal;
            }


            $where  = Compra::query()
                ->where('id_sucursal', $idSucursal) //filtro por sucursal obligatoriamente
                ->leftJoin('compra_producto', 'compra.id_compra', '=', 'compra_producto.id_compra')
                ->leftJoin('proveedor', 'compra.id_proveedor', '=', 'proveedor.id_proveedor')
                ->leftJoin('producto', 'compra_producto.id_producto', '=', 'producto.id_producto')
                ->select(
                    'proveedor.nombre as nombreProveedor',
                    'compra.id_compra',
                    'producto.producto as nombreProducto',
                    'compra_producto.cantidad',
                    'compra.fecha',
                    'proveedor.correo',
                    'proveedor.telefono',
                    'compra.total'
                );

            if ($request->proveedor != null)
                $where = $where->where('proveedor.id_proveedor', $request->proveedor);

            if (($request->busqueda && ($request->tipo === "Nombre")) != null) {
                $where = $where->whereRaw('producto LIKE ?', ['%' . strtolower($request->busqueda) . '%']);
            }

            if (($request->busqueda && ($request->tipo === "Codigo")) != null)

                $where = $where->whereRaw('LOWER(codigo) LIKE ?', ['%' . strtolower($request->busqueda) . '%']); //LOWE PARA convertir en minusculas


            if ($request->metodo_pago != null)
                $where = $where->where('metodo_pago', strtolower($request->metodo_pago));

            if ($request->periodo_compras != null && ($request->periodo_compras === "Ver compras desde el") && (
                $request->fecha_desde != null && $request->fecha_hasta != null)) {
                $fecha_desde = Carbon::parse($request->fecha_desde)->startOfDay(); //toma la fecha desde el comienzo del dia
                $fecha_hasta = Carbon::parse($request->fecha_hasta)->endOfDay(); //toma la fecha hasta el final del dia
                $where = $where->whereBetween('fecha', [$fecha_desde, $fecha_hasta]);
            }

            if ($request->periodo_compras != null && ($request->periodo_compras === "Compras del mes de") && (
                $request->mes_compra != null && $request->anio_compra != null)) {
                $mes = $request->mes_compra;
                $anio = $request->anio_compra;

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
        $anios = Compra::selectRaw('EXTRACT(YEAR FROM fecha) as anio')
            ->distinct()
            ->orderBy('anio')
            ->pluck('anio');

        return response()->json($anios);
    }


    public function generarInforme(Request $request)
    {
        try {
            $usuario = JWTAuth::parseToken()->authenticate();

            $validated = $request->validate([
                'periodo_compras' => 'nullable|string',
                'id_sucursal' => 'nullable|integer',
                'fecha_desde' => 'nullable|date',
                'fecha_hasta' => 'nullable|date',
                'proveedor' => 'nullable|integer',
                'busqueda' => 'nullable|string',
                'tipo' => 'nullable|string',
            ]);

            // Filtrar por sucursal según rol
            if ($usuario->id_rol != config('roles.superadmin')) {
                $validated['id_sucursal'] = $usuario->id_sucursal;
            } else {
                $request->validate([
                    'id_sucursal' => 'required|exists:sucursal,id_sucursal'
                ]);
            }

            // AJUSTAR FILTROS DEPENDIENDO DEL PERÍODO
            if ($request->periodo_compras !== null) {
                if (
                    $request->periodo_compras === "Ver compras desde el" &&
                    $request->fecha_desde !== null && $request->fecha_hasta !== null
                ) {
                    $validated['fecha_desde'] = Carbon::parse($request->fecha_desde)->startOfDay();
                    $validated['fecha_hasta'] = Carbon::parse($request->fecha_hasta)->endOfDay();
                } elseif (
                    $request->periodo_compras === "Compras del mes de" &&
                    $request->mes_compra !== null && $request->anio_compra !== null
                ) {
                    $validated['fecha_desde'] = Carbon::create($request->anio_compra, $request->mes_compra, 1)->startOfMonth();
                    $validated['fecha_hasta'] = Carbon::create($request->anio_compra, $request->mes_compra, 1)->endOfMonth();
                } elseif ($request->periodo_compras === "Todas las compras") {
                    $validated['fecha_desde'] = null;
                    $validated['fecha_hasta'] = null;
                }
            }

            // OBTENER COMPRAS FILTRADAS
            $compras = DB::table('compra')
                ->join('proveedor', 'compra.id_proveedor', '=', 'proveedor.id_proveedor')
                ->leftJoin('compra_producto', 'compra.id_compra', '=', 'compra_producto.id_compra')
                ->leftJoin('producto', 'compra_producto.id_producto', '=', 'producto.id_producto')
                ->select(
                    'compra.id_compra',
                    'compra.fecha',
                    'compra.total',
                    'proveedor.id_proveedor',
                    'proveedor.nombre as proveedor_nombre',
                    'producto.id_producto',
                    'producto.producto as nombre_producto',
                    'compra_producto.cantidad as cantidad_producto',
                    'compra.id_sucursal'
                )
                ->when($validated['id_sucursal'] ?? null, fn($q, $id) => $q->where('compra.id_sucursal', $id))
                ->when($validated['proveedor'] ?? null, fn($q, $id) => $q->where('proveedor.id_proveedor', $id))
                ->when($validated['fecha_desde'] ?? null, fn($q, $f) => $q->whereDate('compra.fecha', '>=', $f))
                ->when($validated['fecha_hasta'] ?? null, fn($q, $f) => $q->whereDate('compra.fecha', '<=', $f))
                ->orderBy('compra.fecha', 'asc')
                ->get();

            if ($compras->isEmpty()) {
                return response()->json([
                    'mensaje' => 'No hay compras registradas en el período seleccionado.',
                    'metricas' => [
                        'total_comprado' => 0,
                        'cantidad_compras' => 0,
                        'productos_comprados' => 0,
                        'proveedores_unicos' => 0,
                    ],
                    'productos_mas_comprados' => [],
                    'compras_por_fecha' => [],
                    'top_proveedores' => [],
                    'productos_por_proveedor' => [],
                ]);
            }

            // CALCULAR MÉTRICAS
            $totalComprado = $compras->sum('total');
            $cantidadCompras = $compras->pluck('id_compra')->unique()->count();
            $cantProductosComprados = $compras->sum('cantidad_producto') ?? 0;
            $proveedoresUnicos = $compras->pluck('id_proveedor')->unique()->count();

            // PRODUCTOS MÁS COMPRADOS
            $productosMasComprados = DB::table('compra_producto')
                ->join('producto', 'producto.id_producto', '=', 'compra_producto.id_producto')
                ->join('compra', 'compra.id_compra', '=', 'compra_producto.id_compra')
                ->when($validated['id_sucursal'] ?? null, fn($q, $id) => $q->where('compra.id_sucursal', $id))
                ->when($validated['proveedor'] ?? null, fn($q, $id) => $q->where('compra.id_proveedor', $id))
                ->when($validated['fecha_desde'] ?? null, fn($q, $f) => $q->whereDate('compra.fecha', '>=', $f))
                ->when($validated['fecha_hasta'] ?? null, fn($q, $f) => $q->whereDate('compra.fecha', '<=', $f))
                ->select('producto.producto', 'producto.costo','producto.foto', DB::raw('SUM(compra_producto.cantidad) as cantidad'))
                ->groupBy('producto.id_producto', 'producto.producto', 'producto.costo','producto.foto')
                ->orderByDesc('cantidad')
                ->limit(5)
                ->get();

            // COMPRAS POR FECHA
            $comprasPorFecha = $compras
                ->groupBy(fn($c) => date('Y-m-d', strtotime($c->fecha)))
                ->map(fn($comprasDia) => [
                    'fecha' => date('d/m', strtotime($comprasDia->first()->fecha)),
                    'Total' => round($comprasDia->sum('total'), 2),
                ])
                ->values();

            // TOP PROVEEDORES
            $topProveedores = $compras
                ->groupBy('id_proveedor')
                ->map(function ($comprasProveedor) {
                    $proveedor = $comprasProveedor->first();
                    return [
                        'nombre' => $proveedor->proveedor_nombre,
                        'compras' => $comprasProveedor->pluck('id_compra')->unique()->count(),
                        'total_comprado' => round($comprasProveedor->sum('total'), 2),
                    ];
                })
                ->sortByDesc('compras')
                ->take(5)
                ->values();

            return response()->json([
                'metricas' => [
                    'total_comprado' => round($totalComprado, 2),
                    'cantidad_compras' => $cantidadCompras,
                    'productos_comprados' => $cantProductosComprados,
                ],
                'productos_mas_comprados' => $productosMasComprados,
                'compras_por_fecha' => $comprasPorFecha,
                'top_proveedores' => $topProveedores,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al generar informe de compras',
                'detalle' => $e->getMessage(),
                'linea' => $e->getLine(),
            ], 500);
        }
    }
}
