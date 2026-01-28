<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSucursalRequest;
use App\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;


class SucursalController extends Controller
{

    public function index()
    {
        return Sucursal::all()->map(function ($suc) {
            // Construir dirección completa: direccion, ciudad

            if ($suc->foto) {
                $suc->imagen = Storage::url($suc->foto);
                unset($suc->foto); // Eliminamos la ruta guardada, dejamos solo imagen
            }
            return $suc;
        });
    }


    public function store(StoreSucursalRequest $request)
    {

        $datosValidos = $request->validated();
        // SI LLEGA FOTO, LA GUARDAMOS EN storage/app/public/sucursales
        if ($request->hasFile('foto')) {
            $ruta = $request->file('foto')->store('sucursales', 'public');
            $datosValidos['foto'] = $ruta; // Guardar SOLO la ruta en BD
        }

        $sucursal = Sucursal::create($datosValidos);
        $sucursal->save();
        return ($sucursal);
    }


    public function show(string $id)
    {
        $sucursal = Sucursal::find($id);

        if (!$sucursal) {
            return response()->json(['error' => 'Sucursal no encontrada'], 404);
        }

        // Convertir ruta en URL pública
        if ($sucursal->foto) {
            $sucursal->foto_url = Storage::url($sucursal->foto);
        }

        return response()->json($sucursal);
    }


    public function update(Request $request, string $id)
    {
        $sucursal = Sucursal::find($id);
        if (!$sucursal) {
            return response()->json(['error' => 'Sucursal no encontrada'], 404);
        }

        $validated = $request->validate([
            'nombre' => [
                'required',
                'max:100',
                Rule::unique('sucursal', 'nombre')->ignore($id, 'id_sucursal'),
            ],
            'direccion' => [
                'required',
                'max:200',
                Rule::unique('sucursal', 'direccion')->ignore($id, 'id_sucursal'),
            ],
            'ciudad' => ['required'],
            'provincia' => ['required'],
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
        ], [
            'nombre.unique' => 'El nombre ya existe, ingrese uno diferente.',
            'direccion.unique' => 'La direccion ya existe, ingrese una diferente.',
            'ciudad.required' => 'Ingese Ciudad.',
            'provincia.required' => 'Ingrese Provincia.',
        ]);

        // Manejo de eliminación de imagen
        if ($request->has('eliminar_foto')) {
            if ($sucursal->foto && Storage::disk('public')->exists($sucursal->foto)) {
                Storage::disk('public')->delete($sucursal->foto);
            }
            $validated['foto'] = null;
        }
        // Manejo de nueva imagen
        else if ($request->hasFile('foto')) {
            // Borrar foto anterior si existe
            if ($sucursal->foto && Storage::disk('public')->exists($sucursal->foto)) {
                Storage::disk('public')->delete($sucursal->foto);
            }
            $ruta = $request->file('foto')->store('sucursales', 'public');
            $validated['foto'] = $ruta;
        }

        $sucursal->update($validated);

        // Generar URL de la imagen actualizada
        if ($sucursal->foto) {
            $sucursal->foto_url = Storage::url($sucursal->foto);
        }

        return response()->json([
            'message' => 'Sucursal modificada correctamente',
            'sucursal' => $sucursal
        ], 200);
    }

    public function destroy(string $id)
    {
        //
    }
}
