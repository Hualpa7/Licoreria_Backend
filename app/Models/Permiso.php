<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Permiso extends Model
{
    protected $table = 'permisos';
    protected $primaryKey  = 'id_permiso';
    public $timestamps = false;


    protected $fillable = [
        'nombre_permiso'
    ];

    protected function nombrePermiso(): Attribute{
        return Attribute::make(
               get: fn (string $value) => ucfirst(strtolower($value)),
               set: fn (string $value) => strtolower($value),
        );
    }

   // Relación muchos a muchos con Rol
    public function roles()
{
    return $this->belongsToMany(
        Rol::class,
        'rol_permiso',
        'id_permiso',
        'id_rol'
    );
}

}
