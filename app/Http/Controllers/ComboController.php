<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreComboRequest;
use App\Http\Requests\UpdateComboRequest;
use App\Models\Combo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

use Tymon\JWTAuth\Facades\JWTAuth;

class ComboController extends Controller
{

    public function index()
    {
        $combo = DB::table('combo')
            ->leftJoin('combo_producto', 'combo.id_combo', '=', 'combo_producto.id_combo')
            ->leftJoin('producto', 'combo_producto.id_producto', '=', 'producto.id_producto')
            ->where('combo.activo', true)
            ->select(
                'combo.id_combo',
                'combo.codigo',
                'combo.nombre',
                'combo.costo',
                'combo.duracion',
                DB::raw('json_agg(json_build_object(
                \'producto\',producto.producto,
                \'cantidad\',combo_producto.cantidad
                )) as productos')
            )
            ->groupBy('combo.id_combo')
            ->get();

        // Decodificar la cadena JSON del array productos
        $combo = $combo->map(function ($item) {
            $item->productos = json_decode($item->productos);
            return $item;
        });


        return response()->json($combo);
    }

    public function mostrarDesactivados(Request $request)
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
            $combo = DB::table('combo')
                ->where('combo.id_sucursal', $idSucursal) //filtro por sucursal obligatoriamente
                ->leftJoin('combo_producto', 'combo.id_combo', '=', 'combo_producto.id_combo')
                ->leftJoin('producto', 'combo_producto.id_producto', '=', 'producto.id_producto')
                ->where('combo.activo', false)
                ->select(
                    'combo.id_combo',
                    'combo.codigo',
                    'combo.nombre',
                    'combo.costo',
                    'combo.duracion',
                    DB::raw('json_agg(json_build_object(
                \'producto\',producto.producto,
                \'cantidad\',combo_producto.cantidad
                )) as productos')
                )
                ->groupBy('combo.id_combo')
                ->get();

            // Decodificar la cadena JSON del array productos
            $combo = $combo->map(function ($item) {
                $item->productos = json_decode($item->productos);
                return $item;
            });


            return response()->json($combo);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al ontener combos desactivados.',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }


    public function store(StoreComboRequest $request)
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


            DB::transaction(function () use ($datosValidos, $request, $idSucursal) { //envolvemos todo en una transaccion para que se haga tanto 
                //la creacion del combo como el registro en la tabla combo_producto

                // SI LLEGA FOTO, LA GUARDAMOS EN storage/app/public/productos
                if ($request->hasFile('foto')) {
                    $ruta = $request->file('foto')->store('combos', 'public');
                    $datosValidos['foto'] = $ruta; // Guardar SOLO la ruta en BD
                }
                $combo = Combo::create(array_merge($datosValidos, [
                    'id_sucursal' => $idSucursal
                ]));

                $productos = json_decode($request->productos, true) ?? [];


                foreach ($productos as $producto) { //para cada uno de los productos del array, los vinculo con el id_combo creado
                    DB::table('combo_producto')->insert([
                        'id_combo' => $combo->id_combo,
                        'id_producto' => $producto['id_producto'],
                        'cantidad' => $producto['cantidad']
                    ]);
                }
            });

            return response()->json(['message' => 'Combo creado exitosamente'], 201);
        } catch (\Exception $e) {
            // Capturamos cualquier error dentro de la transacción
            return response()->json([
                'error' => 'Error al crear combo.',
                'detalle' => $e->getMessage(), // <-- mensaje personalizado
            ], 400);
        }
    }


    public function show(string $id)
    {

        $combo = DB::table('combo')
            ->where('combo.id_combo', $id)
            ->leftJoin('combo_producto', 'combo.id_combo', '=', 'combo_producto.id_combo')
            ->leftJoin('producto', 'combo_producto.id_producto', '=', 'producto.id_producto')
            ->select(
                'combo.es_combo',
                'combo.codigo',
                'combo.id_combo',
                'combo.nombre',
                'combo.costo',
                'combo.duracion',
                'combo.foto',
                DB::raw('json_agg(json_build_object(
                \'producto\',producto.producto,
                \'id_producto\',producto.id_producto,
                \'cantidad\',combo_producto.cantidad
                )) as productos')
            )
            ->groupBy('combo.id_combo')
            ->first();

        if (!$combo) return response()->json(['error' => 'Combo no encontrado'], 404);
        //decodifciar en JSON
        $combo->productos = json_decode($combo->productos);
        if ($combo->foto) {
            $combo->foto_url = Storage::url($combo->foto);
        }

        return response()->json($combo);
    }

    public function update(UpdateComboRequest $request, string $id)
    {
        //PRIMERO HAGO LAS VALIDACIONES PARA NO INGRESAR UN CODIGO/NOMBRE QUE YA ESTE EN LA TABLA (SIN CONTAR AL QUE ESTOY PASANDO)
        if (Combo::where('codigo', $request->codigo)->where('id_combo', '!=', $id)->exists()) {
            return response()->json([
                'message' => 'El código ya está en uso por otro combo.',
            ], 422);
        }

        if (Combo::where('nombre', $request->nombre)->where('id_combo', '!=', $id)->exists()) {
            return response()->json([
                'message' => 'El nombre del combo ya está en uso por otro.',
            ], 422);
        }
        $datosValidos = $request->validated();
        $combo = Combo::findOrFail($id);

        // Manejo de eliminación de imagen
        if ($request->has('eliminar_foto')) {
            if ($combo->foto && Storage::disk('public')->exists($combo->foto)) {
                Storage::disk('public')->delete($combo->foto);
            }
            $datosValidos['foto'] = null;
        }
        // Manejo de nueva imagen
        else if ($request->hasFile('foto')) {
            // Borrar foto anterior si existe
            if ($combo->foto && Storage::disk('public')->exists($combo->foto)) {
                Storage::disk('public')->delete($combo->foto);
            }
            $ruta = $request->file('foto')->store('combos', 'public');
            $datosValidos['foto'] = $ruta;
        }

        $combo->update($datosValidos);
        return response()->json([
            'message' => 'Combo actualizado exitosamente',
            'producto' => $combo
        ], 200);
    }

    public function destroy(string $id)
    {
        //
    }


    public function filtro(Request $request)
    {
        try {
            // Autenticar usuario desde el token
            $usuario = JWTAuth::parseToken()->authenticate();

            // Determinar sucursal
            if ($usuario->id_rol != 5) {
                $idSucursal = $usuario->id_sucursal;
            } else {
                $request->validate([
                    'id_sucursal' => 'required|exists:sucursal,id_sucursal'
                ]);
                $idSucursal = $request->id_sucursal;
            }

            $where = DB::table('combo')
                ->where('combo.id_sucursal', $idSucursal)
                ->leftJoin('combo_producto', 'combo.id_combo', '=', 'combo_producto.id_combo')
                ->leftJoin('producto', 'combo_producto.id_producto', '=', 'producto.id_producto')
                ->where('combo.activo', true)
                ->select(
                    'combo.id_combo',
                    'combo.codigo',
                    'combo.nombre',
                    'combo.costo',
                    'combo.duracion',
                    DB::raw('json_agg(json_build_object(
                    \'producto\', producto.producto,
                    \'cantidad\', combo_producto.cantidad
                )) as productos')
                )
                ->groupBy('combo.id_combo');

            // 🔍 Aplica filtro si hay texto en búsqueda
            if ($request->filled('busqueda')) {
                $where->whereRaw('LOWER(combo.nombre) LIKE ?', ['%' . strtolower($request->busqueda) . '%']);
            }

            $result = $where->get();

            // Decodificar productos
            $result->transform(function ($item) {
                $item->productos = json_decode($item->productos);
                return $item;
            });

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al filtrar combos.',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    public function buscar(Request $request)
    {
        $termino = $request->termino;
        $tipoBusqueda = $request->tipoBusquedaCombo;


        if ($tipoBusqueda != null && $tipoBusqueda === 'Nombre') {
            $resultados = Combo::whereRaw('LOWER(nombre) LIKE ?', ['%' . strtolower($termino) . '%'])
                ->get();
        }
        if ($tipoBusqueda != null && $tipoBusqueda === 'Codigo') {
            $resultados = Combo::whereRaw('LOWER(codigo) LIKE ?', ['%' . strtolower($termino) . '%'])
                ->get();
        }


        return response()->json($resultados);
    }

    public function desactivar($id)
    {
        $combo = Combo::findOrFail($id);
        $combo->activo = false;
        $combo->save();

        return response()->json([
            'message' => 'Combo desactivado correctamente',
            'combo' => $combo
        ]);
    }

    public function activar($id, Request $request)
    {
        // Convertir fecha al formato correcto
        /*  if ($request->has('nuevo_vencimiento')) {
        try {
            $fecha = Carbon::createFromFormat('d/m/Y', $request->nuevo_vencimiento);
            $request->merge(['nuevo_vencimiento' => $fecha->format('Y-m-d')]);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => ['nuevo_vencimiento' => ['El formato de la fecha no es válido (use dd/mm/yyyy).']]
            ], 422);
        }
    }
*/
        $request->validate([
            'nuevo_vencimiento' => 'required|date|after:today',
        ], [
            'nuevo_vencimiento.required' => 'Ingrese una fecha',
            'nuevo_vencimiento.after' => 'La fecha de duración debe ser posterior al día actual.',
        ]);

        $combo = Combo::findOrFail($id);
        $combo->duracion = $request->nuevo_vencimiento;
        $combo->activo = true;
        $combo->save();

        return response()->json([
            'message' => 'Combo activado correctamente',
            'combo' => $combo
        ]);
    }


    //COMBOSQUE SE MSTRARRAN EN INICIO
    public function mostrarActivados()
    {
        try {
            // Obtener todos los combos activos de TODAS las sucursales
            $combos = DB::table('combo')
                ->where('combo.activo', true)
                ->leftJoin('combo_producto', 'combo.id_combo', '=', 'combo_producto.id_combo')
                ->leftJoin('producto', 'combo_producto.id_producto', '=', 'producto.id_producto')
                ->leftJoin('sucursal', 'combo.id_sucursal', '=', 'sucursal.id_sucursal')
                ->select(
                    'combo.id_combo',
                    'combo.nombre',
                    'combo.costo as precio',
                    'combo.foto',
                    'sucursal.nombre as sucursal',
                    DB::raw('json_agg(json_build_object(
                    \'producto\', producto.producto,
                    \'cantidad\', combo_producto.cantidad
                )) as productos')
                )
                ->groupBy('combo.id_combo', 'sucursal.nombre')
                ->get()
                ->map(function ($item) {
                    // Decodificar productos
                    $item->productos = json_decode($item->productos, true) ?? [];

                    // Convertir foto a URL pública (solo si existe)
                    if ($item->foto) {
                        $item->imagen = Storage::url($item->foto);
                        unset($item->foto);
                    }

                    // Convertir costo a número
                    $item->precio = (float)str_replace(',', '.', $item->precio);

                    return $item;
                });

            return response()->json($combos);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener combos activos.',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }
}
