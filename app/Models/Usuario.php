<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;


class Usuario extends Authenticatable implements JWTSubject
{
    protected $table = 'usuario';
    protected $primaryKey  = 'id_usuario';
    public $timestamps = false;

    use HasFactory, Notifiable;

    protected $fillable = [
        'nombre',
        'apellido',
        'dni',
        'contraseña',
        'id_rol',
        'id_sucursal',
        'correo'
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'correo' => $this['correo'],
            'contraseña' => $this['contraseña'],
            'id_usuario' => $this['id_usuario'],
            'id_rol' => $this['id_rol'],
            'id_sucursal' => $this['id_sucursal'],
        ];
    }

    // Relación con la marca
    public function rol()
    {
        return $this->belongsTo(Rol::class, 'id_rol');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal');
    }

    public function tienePermiso($nombrePermiso)
    {
        $nombrePermiso = strtolower($nombrePermiso);

        // Primero chequeamos los permisos del rol
        $permisosRol = $this->rol?->permisos ?? collect();

        $rolTienePermiso = $permisosRol->contains(function ($permiso) use ($nombrePermiso) {
            return strtolower($permiso->nombre_permiso) === $nombrePermiso;
        });

        // Permisos extra
        $permisoExtra = DB::table('permisos_extra')
            ->join('permisos', 'permisos_extra.id_permiso', '=', 'permisos.id_permiso')
            ->where('permisos_extra.id_usuario', $this->id_usuario)
            ->whereRaw('LOWER(permisos.nombre_permiso) = ?', [$nombrePermiso])
            ->where(function ($query) {
                $query->whereNull('expiracion_permiso')
                    ->orWhere('expiracion_permiso', '>', now());
            })
            ->exists();

        return $rolTienePermiso || $permisoExtra;
    }
}
