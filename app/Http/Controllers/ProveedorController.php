<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProveedorRequest;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class ProveedorController extends Controller
{

    public function index()
    {
        return Proveedor::orderBy('nombre', 'asc')->get();
    }



    public function store(StoreProveedorRequest $request)
    {
        $datosValidos = $request->validated();
        $proveedor = Proveedor::create($datosValidos);
        $proveedor->save();
        return ($proveedor);
    }



    public function show(string $id)
    {
        return Proveedor::find($id);
    }


    public function update(Request $request, string $id)
    {
        $proveedor = Proveedor::find($id);
        if (!$proveedor) {
            return response()->json(['error' => 'Proveedor no encontrado'], 404);
        }

        // Validación completa con ignorar el registro actual
        $validated = $request->validate([
            'nombre' => [
                'required',
                'max:100',
                Rule::unique('proveedor', 'nombre')->ignore($id, 'id_proveedor'),
            ],
            'telefono' => [
                'required',
                'regex:/^\d{3,4}-\d{6,7}$/',
                Rule::unique('proveedor', 'telefono')->ignore($id, 'id_proveedor'),
            ],
            'correo' => [
                'required',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z]+\.[a-zA-Z]{2,3}$/',
                Rule::unique('proveedor', 'correo')->ignore($id, 'id_proveedor'),
            ],
        ], [
            'nombre.unique' => 'El nombre ya existe, ingrese uno diferente.',
            'telefono.unique' => 'El teléfono ya existe, ingrese uno diferente.',
            'telefono.regex' => 'Formato de teléfono no válido (ej: 3878-123456).',
            'correo.unique' => 'El correo ya existe, ingrese uno diferente.',
            'correo.regex' => 'Formato de correo no válido.',
        ]);

        $proveedor->update($validated);

        return response()->json([
            'message' => 'Proveedor modificado correctamente',
            'proveedor' => $proveedor
        ], 200);
    }


    public function destroy(string $id)
    {
        //
    }

    public function buscar(Request $request)
    {
        $termino = $request->termino;
        $tipoBusqueda = $request->tipoBusquedaProveedor;


        if ($tipoBusqueda != null && $tipoBusqueda === 'Nombre') {
            $resultados = Proveedor::whereRaw('nombre LIKE ?', ['%' . strtolower($termino) . '%'])
                ->get();
        }
        if ($tipoBusqueda != null && $tipoBusqueda === 'Correo') {
            $resultados = Proveedor::whereRaw('correo LIKE ?', ['%' . $termino . '%'])
                ->get();
        }

        return response()->json($resultados);
    }
}
