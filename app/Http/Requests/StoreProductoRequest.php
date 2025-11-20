<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'codigo' => 'required|string|max:50|unique:producto,codigo', // El código debe ser único en la tabla producto en el campo codigo
            'producto' => 'required|string|max:100|unique:producto,producto',
            'alerta_minima' => 'required|integer', // Debe ser un entero
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'costo' => 'required|regex:/^\d+(\,\d{1,2})?$/', // Validación para que sea decimal con 2 decimales. Hasta aqui sigue siendo un string
            'id_categoria' => [
                'required',
                'integer',
                Rule::exists('categoria', 'id_categoria') // Verifica que el id exista en la tabla de categorías
            ],
            'id_marca' => [
                'required',
                'integer',
                Rule::exists('marca', 'id_marca') // Verifica que el id exista en la tabla de marcas
            ],
            'id_descuento' => 'nullable|integer|exists:descuento,id_descuento' // La verifiacion de exisitencia tmb se puede hacer asi
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.unique' => 'El código ya existe, por favor ingrese uno diferente.',
            'alerta_minima.integer' => 'La alerta mínima debe ser un número entero.',
            'costo.regex' => 'El costo debe ser un número con hasta 2 decimales.',
            'id_categoria.exists' => 'La categoría seleccionada no existe.',
            'id_marca.exists' => 'La marca seleccionada no existe.',
        ];
    }
}
