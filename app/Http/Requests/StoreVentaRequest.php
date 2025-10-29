<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVentaRequest extends FormRequest
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
            'total' => 'required|regex:/^\d+(\.\d{1,2})?$/',
            'metodo_pago' => 'required|string|max:50',
            'descuento_gral' => 'integer',
            'productos' => 'required|array',
            'productos.*.id_producto' => 'integer|exists:producto,id_producto',
            'productos.*.Cantidad' => 'integer|min:1',
            'productos.*.IVA' => 'integer'
        ];
    }

    public function messages(): array
    {
        return [
            'total.regex' => 'El valor debe ser un numero decimal con 2 decimales',
            'metodo_pago.required' => 'Ingrese metodo de pago',
            'productos.required' => 'Debe agregar al menos un producto',
            'productos.*.id_producto.exists' => 'El producto no existe',
            'productos.*.Cantidad.min' => 'La cantidad debe ser al menos 1',
            'productos.*.IVA.required' => 'Introduzca IVA'
        ];
    }
}
