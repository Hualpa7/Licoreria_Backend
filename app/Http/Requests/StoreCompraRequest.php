<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompraRequest extends FormRequest
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
            'total' => 'required|regex:/^\d+(\,\d{1,2})?$/',
            'id_proveedor' => 'required|integer|exists:proveedor,id_proveedor',
            'id_producto' => 'required|integer|exists:producto,id_producto',
            'cantidad' => 'required'
        ];
    }

    public function messages(): array
    {
        return [
            'total.regex' => 'El valor debe ser un numero decimal con 2 decimales',
            'id_proveedor.exists' => 'El proveedor al que realizo la compra no esta registrado',
        ];
    }
}
