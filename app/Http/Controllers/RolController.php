<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRolRequest;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RolController extends Controller
{

    public function index()
    {

        // Consulta para obtener los roles y permisos en formato JSON
        $roles = DB::table('roles')
            ->leftJoin('rol_permiso', 'roles.id_rol', '=', 'rol_permiso.id_rol')
            ->leftJoin('permisos', 'rol_permiso.id_permiso', '=', 'permisos.id_permiso')
            ->select(
                'roles.id_rol',
                'roles.nombre_rol',
                DB::raw('COALESCE(json_agg(json_build_object(
                        \'id_permiso\', permisos.id_permiso,
                        \'nombre_permiso\', permisos.nombre_permiso
                    )
                ) FILTER (WHERE permisos.id_permiso IS NOT NULL),
                \'[]\'
            ) AS permisos
        ')
            )
            ->groupBy('roles.id_rol')
            ->get();

        // Decodificar los permisos en formato JSON
        foreach ($roles as $rol) {
            $rol->permisos = json_decode($rol->permisos);
        }

        // Retornar el resultado como JSON
        return response()->json($roles);
    }

    public function store(StoreRolRequest $request)
    {
        $datosValidos = $request->validated();
        $permisos = $request->input('permisos', []); // array de ids de permisos

        try {
            DB::beginTransaction();

            // 1️⃣ Crear el rol
            $rol = Rol::create($datosValidos);

            // 2️⃣ Vincular permisos en la tabla pivote 'rol_permiso'
            if (!empty($permisos)) {
                // Validar permisos existentes
                $permisosValidos = DB::table('permisos')
                    ->whereIn('id_permiso', $permisos)
                    ->pluck('id_permiso')
                    ->toArray();

                // Esto genera automáticamente los inserts en rol_permiso
                $rol->permisos()->sync($permisosValidos);
            }

            DB::commit();

            return response()->json([
                'rol' => $rol,
                'message' => 'Rol creado y permisos vinculados exitosamente'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Hubo un error al crear el rol con permisos',
                'details' => $e->getMessage()
            ], 500);
        }
    }



    public function show(string $id)
    {
        $rol = DB::table('roles')
            ->leftJoin('rol_permiso', 'roles.id_rol', '=', 'rol_permiso.id_rol')
            ->leftJoin('permisos', 'rol_permiso.id_permiso', '=', 'permisos.id_permiso')
            ->select(
                'roles.id_rol',
                'roles.nombre_rol',
                DB::raw('COALESCE(json_agg(json_build_object(
                    \'id_permiso\', permisos.id_permiso,
                    \'nombre_permiso\', permisos.nombre_permiso
                )
            ) FILTER (WHERE permisos.id_permiso IS NOT NULL),
            \'[]\'
        ) AS permisos')
            )
            ->where('roles.id_rol', $id)
            ->groupBy('roles.id_rol')
            ->first(); // solo un resultado

        if ($rol) {
            $rol->permisos = json_decode($rol->permisos);
            return response()->json($rol);
        }

        return response()->json(['message' => 'Rol no encontrado'], 404);
    }



    public function update(Request $request, string $id)
    {
        //
    }


    public function destroy(string $id)
    {
        //
    }
}
