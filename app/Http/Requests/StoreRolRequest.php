<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRolRequest extends FormRequest
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
            'nombre_rol' => 'required|unique:roles,nombre_rol|max:100',
            'permisos' => 'required|array|min:1', // Array obligatorio con al menos 1 elemento
            'permisos.*' => 'integer|exists:permisos,id_permiso', // cada elemento debe existir
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_rol.unique' => 'El rol ya existe, ingrese uno diferente.',
            'permisos.required' => 'Debe seleccionar al menos un permiso.',
            'permisos.min' => 'Debe seleccionar al menos un permiso.',
            'permisos.*.exists' => 'Uno de los permisos seleccionados no es válido.',
        ];
    }
}
