<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table = 'roles';
    protected $primaryKey  = 'id_rol';
    public $timestamps = false;

    protected $fillable = [
        'nombre_rol'
    ];

    protected function nombreRol(): Attribute{
        return Attribute::make(
               get: fn (string $value) => ucfirst(strtolower($value)),
               set: fn (string $value) => strtolower($value),
        );
    }

    // Relación muchos a muchos con Permiso
    public function permisos()
{
    return $this->belongsToMany(
        Permiso::class,
        'rol_permiso',   // tabla pivote
        'id_rol',        // FK local
        'id_permiso'     // FK relacionada
    );
}


    
}
