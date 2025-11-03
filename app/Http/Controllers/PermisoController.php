<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePermisoRequest;
use App\Models\Permiso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PermisoController extends Controller
{

    public function index()
    {
        return Permiso::all();
    }


    public function store(StorePermisoRequest $request)
    {

        $datosValidos = $request->validated();
        $permiso = Permiso::create($datosValidos);
        $permiso->save();
        return ($permiso);
    }

    public function show(string $id)
    {
        return Permiso::find($id);
    }


    public function update(Request $request, string $id)
    {
        //
    }


    public function destroy(string $id)
    {
        //
    }

    public function vincularPermisoaRol(Request $request)
    {
        // Validar los datos entrantes
        $validatedData = $request->validate([
            'id_rol' => 'required|integer|exists:roles,id_rol',
            'permisos' => 'required|array',
            'permisos.*' => 'integer|exists:permisos,id_permiso',
        ]);

        $idRol = $validatedData['id_rol'];
        $permisos = $validatedData['permisos'];

        try {
            // Iniciar una transacción para mayor seguridad
            DB::beginTransaction();

            // Eliminar todos los permisos asociados previamente al rol (si es necesario)
            DB::table('rol_permiso')->where('id_rol', $idRol)->delete();

            // Insertar los nuevos permisos
            $data = [];
            foreach ($permisos as $idPermiso) {
                $data[] = [
                    'id_rol' => $idRol,
                    'id_permiso' => $idPermiso,
                ];
            }
            DB::table('rol_permiso')->insert($data);

            // Confirmar los cambios
            DB::commit();

            return response()->json([
                'message' => 'Permisos vinculados exitosamente al rol.',
            ], 200);
        } catch (\Exception $e) {
            // Revertir los cambios si ocurre un error
            DB::rollBack();
            return response()->json([
                'error' => 'Hubo un error al vincular los permisos.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    
}
