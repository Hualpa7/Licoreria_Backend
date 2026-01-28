<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;

class UsuarioController extends Controller
{

    public function index()
    {
        return Usuario::with('sucursal', 'rol')->get();
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

        // NUEVOOO Verificar que el usuario esté activo
        if (!$usuario->activo) {
            return response()->json(['error' => 'Usuario dado de baja. Contacte al administrador'], 403);
        }

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
        try {
            $datosUsuario = DB::table('usuario')
                ->where('usuario.id_usuario', $id)
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

            if (!$datosUsuario) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }

            // Decodificar las cadenas JSON de cada campo
            $datosUsuario->sucursal = json_decode($datosUsuario->sucursal);
            $datosUsuario->rol = json_decode($datosUsuario->rol);
            $datosUsuario->permisos = json_decode($datosUsuario->permisos);
            $datosUsuario->permisos_extra = json_decode($datosUsuario->permisos_extra);

            return response()->json($datosUsuario);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener el usuario', 'detalle' => $e->getMessage()], 500);
        }
    }



    public function update(Request $request, string $id)
    {
        //
    }


    public function destroy(string $id)
    {
        //
    }

    // MÉTODO PARA DAR DE BAJA UN USUARIO
    public function darDeAltaBaja(Request $request, $id_usuario)
    {
        $request->validate([
            'activo' => 'required|boolean'
        ]);

        try {
            $usuario = Usuario::find($id_usuario);

            if (!$usuario) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }

            // para prevenri que se dé de baja a sí mismo
            $usuarioActual = JWTAuth::parseToken()->authenticate();
            if ($usuarioActual->id_usuario === (int)$id_usuario) {
                return response()->json(['error' => 'No puedes cambiar tu propio estado'], 403);
            }

            $usuario->activo = $request->input('activo');
            $usuario->save();

            $accion = $usuario->activo ? 'reactivado' : 'dado de baja';

            return response()->json([
                'message' => "Usuario $accion correctamente",
                'usuario' => $usuario
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar el estado del usuario',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    //FUNCION PARA AGREGAR O ELIMINAR PERMISOS EXTRA

    public function actualizarPermisosExtra(Request $request, $id_usuario)
    {
        // Validación básica
        $request->validate([
            'permisos_extra' => 'array',
            'permisos_extra.*' => 'integer|exists:permisos,id_permiso'
        ]);

        $permisosExtra = $request->input('permisos_extra', []);

        try {
            DB::beginTransaction();

            //  Obtener los permisos extra actuales del usuario
            $permisosActuales = DB::table('permisos_extra')
                ->where('id_usuario', $id_usuario)
                ->pluck('id_permiso')
                ->toArray();

            //  Calcular permisos a insertar (los que vienen en $permisosExtra pero no están)
            $permisosParaInsertar = array_diff($permisosExtra, $permisosActuales);

            //  Calcular permisos a eliminar (los que están actualmente pero no vienen en $permisosExtra)
            $permisosParaEliminar = array_diff($permisosActuales, $permisosExtra);

            //  Insertar nuevos permisos
            $insertData = [];
            foreach ($permisosParaInsertar as $idPermiso) {
                $insertData[] = [
                    'id_usuario' => $id_usuario,
                    'id_permiso' => $idPermiso,
                    'expiracion_permiso' => null, // o podés recibirlo en el request
                ];
            }

            if (!empty($insertData)) {
                DB::table('permisos_extra')->insert($insertData);
            }

            //  Eliminar permisos que ya no deben existir
            if (!empty($permisosParaEliminar)) {
                DB::table('permisos_extra')
                    ->where('id_usuario', $id_usuario)
                    ->whereIn('id_permiso', $permisosParaEliminar)
                    ->delete();
            }

            DB::commit();

            return response()->json([
                'message' => 'Permisos actualizados correctamente.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al actualizar permisos extra.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    //FUNCIONES RESETEO CONTRASEÑA

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'correo' => 'required|email'
        ]);

        $usuario = Usuario::where('correo', $request->correo)->first();

        if (!$usuario) {
            return response()->json(['message' => 'Correo no encontrado'], 404);
        }

        // Crear el token manualmente
        $token = \Illuminate\Support\Str::random(64);

        // Guardar en la base de datos con el campo 'email' (no 'correo')
        // porque la tabla password_reset_tokens usa 'email'
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $usuario->correo],
            [
                'email' => $usuario->correo,
                'token' => Hash::make($token), // Guardamos el hash del token
                'created_at' => now()
            ]
        );

        // Enviar correo
        try {
            Mail::send('emails.reset_password', [
                'token' => $token,  // Enviamos el token SIN hashear
                'correo' => $usuario->correo
            ], function ($message) use ($usuario) {
                $message->to($usuario->correo);
                $message->subject('Recuperar contraseña - LEYSECA');
            });

            Log::info('Correo de reseteo enviado a: ' . $usuario->correo);

            return response()->json(['message' => 'Correo enviado exitosamente']);
        } catch (\Exception $e) {
            Log::error('Error al enviar correo: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al enviar el correo',
                'error' => $e->getMessage()
            ], 500);
        }
    }




    public function resetPassword(Request $request)
    {
        $request->validate([
            'correo' => 'required|email',
            'token' => 'required|string',
            'contraseña' => 'required|min:6'
        ]);

        $usuario = Usuario::where('correo', $request->correo)->first();

        if (!$usuario) {
            return response()->json(['message' => 'Correo no encontrado'], 404);
        }

        // Buscar el token en la base de datos
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->correo)
            ->first();

        if (!$resetRecord) {
            return response()->json(['message' => 'No se encontró solicitud de reseteo'], 400);
        }

        // Verificar que el token coincida (comparamos el token recibido con el hash guardado)
        if (!Hash::check($request->token, $resetRecord->token)) {
            return response()->json(['message' => 'Token inválido'], 400);
        }

        // Verificar que no haya expirado (60 minutos por defecto)
        $createdAt = \Carbon\Carbon::parse($resetRecord->created_at);
        if ($createdAt->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->correo)->delete();
            return response()->json(['message' => 'Token expirado'], 400);
        }

        // Actualizar contraseña
        $usuario->contraseña = Hash::make($request->contraseña);
        $usuario->save();

        // Eliminar el token usado
        DB::table('password_reset_tokens')->where('email', $request->correo)->delete();

        return response()->json(['message' => 'Contraseña actualizada correctamente']);
    }


    public function cambiarContrasenia(Request $request)
    {
        $request->validate([
            'correo' => 'required|email',
            'contraseña_actual' => 'required',
            'nueva_contraseña' => 'required|min:6',
        ]);

        $usuario = Usuario::where('correo', $request->correo)->first();

        if (!$usuario || !Hash::check($request->contraseña_actual, $usuario->contraseña)) {
            return response()->json([
                'message' => 'La contraseña actual es incorrecta.'
            ], 422);
        }

        $usuario->contraseña = Hash::make($request->nueva_contraseña);
        $usuario->save();

        return response()->json([
            'message' => 'Contraseña cambiada exitosamente.'
        ], 200);
    }
}
