<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'producto';
    protected $primaryKey  = 'id_producto';
    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'producto',
        'alerta_minima',
        'costo',
        'id_categoria',
        'id_marca'
    ];

    protected function producto(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => ucwords(strtolower($value)),
            set: fn(string $value) => strtolower($value),
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

     //actualiza fecha de modificacion
    protected static function booted()
    {
        static::updating(function ($producto) {
            $producto->fecha_modificacion = now();
        });
    }
    //LO SIGUIENTE ES PARA RELACIONAR MI MODELO DE PRODUCTO CON LOS DE MARCA, CATEOGIRA Y STOCK, PARA SER MAS ESCALABLE
    ///Y NO HACER CONSULTAS COMPLEJAS EN EL COONTROLADOR DEL PRODCUTO
    // Relación con la categoría
    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria');
    }

    // Relación con la marca
    public function marca()
    {
        return $this->belongsTo(Marca::class, 'id_marca');
    }

    // Relación con el stock
    public function stock()
    {
        return $this->hasMany(Stock::class, 'id_producto');
    }

    //LUEGO DE LA IMPLEMTENACION DE PRODCUTOS-DESCUENTOS MUCHOS A MUCHOS
    public function descuentos()
    {
        return $this->belongsToMany(Descuento::class, 'producto_descuento', 'id_producto', 'id_descuento')
            ->withPivot('id_sucursal');
    }

    
}
