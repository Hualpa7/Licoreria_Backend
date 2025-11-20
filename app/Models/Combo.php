<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Combo extends Model
{
    protected $table = 'combo';
    protected $primaryKey  = 'id_combo';
    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'costo',
        'duracion',
        'id_sucursal',
        'foto'
    ];

    protected function nombre(): Attribute{
        return Attribute::make(
               get: fn (string $value) => ucfirst(strtolower($value)),
               set: fn (string $value) => strtolower($value),
        );
    }

    protected function codigo(): Attribute{
        return Attribute::make(
               get: fn (string $value) => ucfirst(strtolower($value)),
               set: fn (string $value) => strtolower($value),
        );
    }
    protected function costo(): Attribute
    {
        return Attribute::make(
            // Convertir a string en el formato deseado con coma como separador de decimales
            get: fn($value) => (string)number_format($value, 2, ',', ''),
            // En el set, convertir a float y eliminar puntos en la parte entera
            set: fn($value) => is_string($value)
                ? (float)str_replace(',', '.', $value)
                : $value
        );
    }

    protected function duracion(): Attribute
    {
        return Attribute::make(
            get: fn($value) => date('d/m/Y', strtotime($value)), // Convertir a dd/mm/aaaa al obtenerlo
            set: fn($value) => date('Y-m-d', strtotime($value)) // Guardar en Y-m-d H:i:s al almacenarlo
        );
    }
}
