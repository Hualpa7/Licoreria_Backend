<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSucursalRequest;
use App\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;


class SucursalController extends Controller
{

    public function index()
    {
        return Sucursal::all();
    }


    public function store(StoreSucursalRequest $request)
    {

        $datosValidos = $request->validated();
        $sucursal = Sucursal::create($datosValidos);
        $sucursal->save();
        return ($sucursal);
    }


    public function show(string $id)
    {
        return Sucursal::find($id);
    }


    public function update(Request $request, string $id)
    {
        $sucursal = Sucursal::find($id);
        if (!$sucursal) {
            return response()->json(['error' => 'Sucursal no encontrada'], 404);
        }

        // Validación completa con ignorar el registro actual
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
            'ciudad' => [
                'required',
            ],
            'provincia' => [
                'required',
            ],
        ], [
            'nombre.unique' => 'El nombre ya existe, ingrese uno diferente.',
            'direccion.unique' => 'La direccion ya existe, ingrese una diferente.',
            'ciudad.required' => 'Ingese Ciudad.',
            'provincia.required' => 'Ingrese Provincia.',
        ]);

        $sucursal->update($validated);

        return response()->json([
            'message' => 'Sucursal modificada correctamente',
            'proveedor' => $sucursal
        ], 200);
    }


    public function destroy(string $id)
    {
        //
    }
}
