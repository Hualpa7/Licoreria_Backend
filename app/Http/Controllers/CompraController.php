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
            if ($usuario->id_rol != 5) {
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
            if ($usuario->id_rol != 5) {
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
}
