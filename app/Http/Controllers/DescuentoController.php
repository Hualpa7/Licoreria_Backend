<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDescuentoRequest;
use App\Http\Requests\UpdateDescuentoRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use App\Models\Descuento;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class DescuentoController extends Controller
{

    public function index()
    {
        $descuentos = DB::table('descuento')
            ->join('producto_descuento', 'descuento.id_descuento', '=', 'producto_descuento.id_descuento')
            ->join('producto', 'producto_descuento.id_producto', '=', 'producto.id_producto')
            ->select(
                'descuento.id_descuento',
                DB::raw("INITCAP(producto.producto) as producto"),
                'producto.costo',
                'descuento.porcentaje',
                'descuento.duracion',
                'producto_descuento.id_sucursal'
            )
            ->get();

        return $descuentos;
    }


    public function store(StoreDescuentoRequest $request)
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

            DB::transaction(function () use ($datosValidos, $request, $idSucursal) {
                $idProducto = $request->id_producto;

                // Verificamos si ya existe un descuento para ese producto en esa sucursal
                $existe = DB::table('producto_descuento')
                    ->where('id_producto', $idProducto)
                    ->where('id_sucursal', $idSucursal)
                    ->exists();

                if ($existe) {
                    throw new \Exception('Este producto ya tiene un descuento asignado en esta sucursal.');
                }

                // Creamos el descuento
                $descuento = Descuento::create($datosValidos);

                // Asignamos el producto al descuento mediante la tabla intermedia
                DB::table('producto_descuento')->insert([
                    'id_producto' => $idProducto,
                    'id_descuento' => $descuento->id_descuento,
                    'id_sucursal' => $idSucursal,
                ]);

                return response()->json($descuento, 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al crear descuento.',
                'detalle' => $e->getMessage()
            ], 400);
        }
    }



    public function show(string $id)
    {
        $descuento = DB::table('descuento')
            ->join('producto_descuento', 'descuento.id_descuento', '=', 'producto_descuento.id_descuento')
            ->join('producto', 'producto_descuento.id_producto', '=', 'producto.id_producto')
            ->select(
                'descuento.id_descuento',
                DB::raw("INITCAP(producto.producto) as producto"), // Convierte a mayúscula la primera letra de cada palabra
                'producto.costo',
                'descuento.porcentaje',
                'descuento.duracion',
                'producto_descuento.id_sucursal'
            )
            ->where('descuento.id_descuento', $id)
            ->first();

        return $descuento;
    }




    public function update(UpdateDescuentoRequest $request, string $id)
    {

        $datosValidos = $request->validated();
        $descuento = Descuento::findOrFail($id);
        $descuento->update($datosValidos);

        return response()->json([
            'message' => 'descuento actualizado exitosamente',
            'descuento' => $descuento
        ], 200);
    }

    public function destroy(string $id)
    {
        try {
            DB::transaction(function () use ($id) {
                $descuento = Descuento::find($id);

                if (!$descuento) {
                    return response()->json([
                        'error' => 'Descuento no encontrado'
                    ], 404);
                }

                // Eliminamos todas las relaciones producto_descuento asociadas a este descuento
                DB::table('producto_descuento')
                    ->where('id_descuento', $id)
                    ->delete();

                // Finalmente eliminamos el descuento
                $descuento->delete();
            });

            return response()->json(['message' => 'Descuento eliminado'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al eliminar descuento.',
                'detalle' => $e->getMessage()
            ], 400);
        }
    }



    public function filtro(Request $request)
    {
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

            // Nueva consulta con tabla intermedia
            $query = Descuento::join('producto_descuento', 'descuento.id_descuento', '=', 'producto_descuento.id_descuento')
                ->join('producto', 'producto_descuento.id_producto', '=', 'producto.id_producto')
                ->where('producto_descuento.id_sucursal', $idSucursal)
                ->select(
                    'descuento.id_descuento',
                    'producto.producto',
                    'producto.costo',
                    'descuento.porcentaje',
                    'descuento.duracion'
                );

            // Filtro por texto
            if ($request->filled('busqueda')) {
                $query->whereRaw('LOWER(producto.producto) LIKE ?', ['%' . strtolower($request->busqueda) . '%']);
            }

            return $query->get();
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al filtrar descuentos.',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }
}
