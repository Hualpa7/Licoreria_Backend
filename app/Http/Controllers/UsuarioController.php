<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class UsuarioController extends Controller
{

    public function index()
    {
        return Usuario::all();
    }


    public function store(StoreUsuarioRequest $request)
    {
        $datosValidos = $request->validated();
        $usuario = Usuario::create($datosValidos);
        $usuario->save();
        return ($usuario);
    }

    //REGISTER
    public function register(StoreUsuarioRequest $request)
    {
        $datosValidos = $request->validated();

        $datosValidos['contraseña'] = Hash::make($datosValidos['contraseña']);

        $usuario = Usuario::create($datosValidos);

        $token = JWTAuth::fromUser($usuario);

        return response()->json(compact('usuario', 'token'), 201);
    }

    //LOGIN

    public function login(Request $request)
    {
        $credentials = $request->only('correo', 'contraseña');

        $usuario = Usuario::where(['correo' => $credentials['correo']])->first();


        if (!Hash::check($credentials['contraseña'], $usuario->contraseña))
            return response()->json(['error' => 'Unauthoriezed'], 401);

        $token = JWTAuth::fromUser($usuario);

        return response()->json(compact('token'));
    }


    //GETUSER
    public function getUser()
    {
        try {
            if (! $usuario = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['error' => 'User not found'], 404);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'Token invalido'], 400);
        }

        $idUsuario = $usuario->id_usuario;

        $datosUsuario = DB::table('usuario')
            ->where('usuario.id_usuario', $idUsuario)
            ->leftJoin('sucursal', 'usuario.id_sucursal', '=', 'sucursal.id_sucursal')
            ->leftJoin('roles', 'usuario.id_rol', '=', 'roles.id_rol')
            ->leftJoin('rol_permiso', 'usuario.id_rol', '=', 'rol_permiso.id_rol')
            ->leftJoin('permisos', 'rol_permiso.id_permiso', '=', 'permisos.id_permiso')
            ->leftJoin('permisos_extra', 'usuario.id_usuario', '=', 'permisos_extra.id_usuario')
            ->select(
                'usuario.id_usuario',
                'usuario.nombre',
                'usuario.apellido',
                'usuario.dni',
                'usuario.correo',
                // Subconsulta para sucursales únicas
                DB::raw('(SELECT json_agg(json_build_object(
                \'id_sucursal\', suc.id_sucursal,
                \'nombreSucursal\', suc.nombre)) 
                  FROM sucursal suc
                   WHERE suc.id_sucursal = usuario.id_sucursal
                 ) as sucursal'),
                // Subconsulta para roles únicos
                DB::raw('(SELECT json_agg(json_build_object(
                \'id_rol\', r.id_rol,
                \'nombreRol\', r.nombre_rol)) 
                FROM roles r 
                WHERE r.id_rol = usuario.id_rol
                ) as rol'),
                // Subconsulta para permisos únicos
                DB::raw('(SELECT json_agg(json_build_object(
                \'id_permiso\', perm.id_permiso,
                \'nombrePermiso\', perm.nombre_permiso)) 
                FROM permisos perm 
                JOIN rol_permiso rolperm ON perm.id_permiso = rolperm.id_permiso
                WHERE rolperm.id_rol = usuario.id_rol
               ) as permisos'),
                // Subconsulta para permisos extra
                DB::raw('(SELECT COALESCE(json_agg(json_build_object(
                    \'id_permiso\', p_extra.id_permiso,
                    \'nombrePermiso\', p.nombre_permiso,
                    \'expiracionPermiso\', p_extra.expiracion_permiso)), \'[]\')
                    FROM permisos_extra p_extra
                    JOIN permisos p ON p_extra.id_permiso = p.id_permiso
                    WHERE p_extra.id_usuario = usuario.id_usuario
                ) as permisos_extra')
            )
            ->groupBy('usuario.id_usuario', 'usuario.nombre', 'usuario.apellido')
            ->first();

        // Decodificar la cadena JSON de cada array
        if ($datosUsuario)
            $datosUsuario->sucursal = json_decode($datosUsuario->sucursal);
        $datosUsuario->rol = json_decode($datosUsuario->rol);
        $datosUsuario->permisos = json_decode($datosUsuario->permisos);
        $datosUsuario->permisos_extra = json_decode($datosUsuario->permisos_extra);



        return response()->json($datosUsuario);
    }

    // LOGOUT
    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json(['message' => 'Successfully logged out']);
    }


    public function show(string $id)
    {
        $usuario = Usuario::select('id_usuario', 'id_rol', 'id_sucursal')
            ->with([
                'rol:id_rol,nombre_rol',
                'sucursal:id_sucursal'
            ])
            ->find($id);

        if (!$usuario) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        // Combinar los datos en un solo nivel
        $resultado = [
            'id_usuario' => $usuario->id_usuario,
            'id_rol' => $usuario->id_rol,
            'nombre_rol' => $usuario->rol->nombre_rol ?? null,
            'id_sucursal' => $usuario->id_sucursal,
        ];

        return response()->json($resultado);
    }



    public function update(Request $request, string $id)
    {
        //
    }


    public function destroy(string $id)
    {
        //
    }
}
