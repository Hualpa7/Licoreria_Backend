<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertaStock extends Model
{
    protected $table = 'alerta_stock';
    protected $primaryKey = 'id_alerta';
    public $timestamps = false;

    protected $fillable = [
        'id_producto',
        'producto',
        'id_sucursal',
        'nombre',
        'stock_actual',
        'alerta_minima',
        'fecha_alerta'
    ];

    protected $casts = [
        'fecha_alerta' => 'datetime'
    ];

    // Relaciones
    public function productoRelacion()
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function sucursalRelacion()
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal', 'id_sucursal');
    }
}
