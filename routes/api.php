<?php

use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ComboController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\DescuentoController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\PermisoController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\Usuario2Controller;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VentaController;
use App\Http\Middleware\JwtMiddleware;
use App\Models\Descuento;
use Illuminate\Support\Facades\Route;
use App\Models\Producto;






//Route::get('/combo/mostrarDesactivados', [ComboController::class,'mostrarDesactivados']);
//Route::resource('/categoria',CategoriaController::class);
//Route::resource('/combo',ComboController::class);
//Route::resource('/compra',CompraController::class);
//Route::resource('/descuento',DescuentoController::class);
//Route::resource('/marca',MarcaController::class);
//Route::resource('/permiso',PermisoController::class);
//Route::resource('/producto',ProductoController::class);
//Route::resource('/proveedor',ProveedorController::class);
//Route::resource('/rol',RolController::class);
//Route::resource('/stock',StockController::class);
//Route::resource('/sucursal',SucursalController::class);
//Route::resource('/usuario',UsuarioController::class);
//Route::resource('/venta',VentaController::class);






//RUTAS PROTEGIDAS DE MARCA
Route::resource('/marca',MarcaController::class) ->except(['store', 'update']);
//ruta de Venta con middleware de permiso
Route::middleware('checkPermiso:crear marca')->post('/marca', [MarcaController::class, 'store']);
Route::middleware('checkPermiso:crear marca')->put('/marca/{id}', [MarcaController::class, 'update']); //mismo permiso ya que si puede crear puede modificar

//RUTAS PPROTEGIDAS DE CATEGORIA
Route::resource('/categoria',CategoriaController::class) ->except(['store', 'update']);
Route::post('/categoria/filtro', [CategoriaController::class,'filtro']);
//ruta de Venta con middleware de permiso
Route::middleware('checkPermiso:crear categoria')->post('/categoria', [CategoriaController::class, 'store']);
Route::middleware('checkPermiso:crear categoria')->put('/categoria/{id}', [CategoriaController::class, 'update']);

//RUTAS PROTEGIDAS DE COMPRA
Route::get('/compra/anios', [CompraController::class,'obtenerAnios']);
Route::resource('/compra',CompraController::class) ->except(['store']);
Route::post('/compra/filtro', [CompraController::class,'filtro']);
//ruta de compra con middleware de permiso
Route::middleware('checkPermiso:comprar')->post('/compra', [CompraController::class, 'store']);

//RUTAS PROTEGIDAS DE VENTA
Route::get('/venta/cantidadTotalVentas', [VentaController::class,'cantidadTotalVentas']);
Route::get('/venta/anios', [VentaController::class,'obtenerAnios']);
Route::resource('/venta',VentaController::class) ->except(['store']); 
Route::post('/venta/filtro', [VentaController::class,'filtro']);


//ruta de Venta con middleware de permiso
Route::middleware('checkPermiso:vender')->post('/venta', [VentaController::class, 'store']);
Route::middleware('checkPermiso:ver informe')->post('/venta/informe', [VentaController::class, 'generarInforme']);

//RUTAS PROTEGIDAS DE PRODUCTO
Route::resource('/producto',ProductoController::class) ->except(['store', 'update']);
Route::post('/producto/filtro', [ProductoController::class,'filtro']);
Route::post('/producto/buscar', [ProductoController::class,'buscar']);
Route::post('/producto/{id}/mostrar2', [ProductoController::class,'mostrar2']);
//ruta de Porducto con middleware de permiso
Route::middleware('checkPermiso:crear producto')->post('/producto', [ProductoController::class, 'store']);
Route::middleware('checkPermiso:modificar producto')->put('/producto/{id}', [ProductoController::class, 'update']);
Route::middleware('checkPermiso:transferir producto')->post('/producto/transferir', [ProductoController::class, 'transferir']);


//RUTAS PROTEGIDAS DE SUCURSAL
Route::resource('/sucursal',SucursalController::class) ->except(['store', 'update']);
//ruta de Sucursal con middleware de permiso
Route::middleware('checkPermiso:crear sucursal')->post('/sucursal', [SucursalController::class, 'store']);
Route::middleware('checkPermiso:crear sucursal')->put('/sucursal/{id}', [SucursalController::class, 'update']);

//RUTAS PROTEGIDAS DE USUARIO
Route::resource('/usuario',UsuarioController::class) ->except(['store', 'update']);
Route::post('/usuario/iniciarSesion', [UsuarioController::class,'login']);
//Route::post('/usuario/registrar', [UsuarioController::class,'register']);
//rutas de Usuario con middleware de permiso
Route::middleware('checkPermiso:crear usuario')->post('/usuario/registrar', [UsuarioController::class, 'register']);
Route::middleware('checkPermiso:crear usuario')->put('/usuario/{id}', [UsuarioController::class, 'update']);
Route::middleware('checkPermiso:modificar permisos')->post('/usuario/{id}/permisos_extra', [UsuarioController::class, 'actualizarPermisosExtra']);
//rutas de Usuario con middleware autenticacion
Route::middleware([JwtMiddleware::class])->group(function () {
    Route::post('/usuario/cerrarSesion', [UsuarioController::class,'logout']);
    Route::post('/usuario/usuario', [UsuarioController::class,'getUser']);
});


//RUTAS PROTEGIDAS DE PROVEEDOR
Route::resource('/proveedor',ProveedorController::class) ->except(['store', 'update']);
Route::post('/proveedor/buscar', [ProveedorController::class,'buscar']);
//ruta de Proveedor con middleware de permiso   
Route::middleware('checkPermiso:crear proveedor')->post('/proveedor', [ProveedorController::class, 'store']);
Route::middleware('checkPermiso:crear proveedor')->put('/proveedor/{id}', [ProveedorController::class, 'update']);

//RUTAS PROTEGIDAS DE DESCUENTO
Route::resource('/descuento',DescuentoController::class) ->except(['store', 'update', 'destroy']);
Route::post('/descuento/filtro', [DescuentoController::class,'filtro']);
//ruta de Descuento con middleware de permiso
Route::middleware('checkPermiso:crear descuento')->post('/descuento', [DescuentoController::class, 'store']);
Route::middleware('checkPermiso:crear descuento')->put('/descuento/{id}', [DescuentoController::class, 'update']);
Route::middleware('checkPermiso:crear descuento')->delete('/descuento/{id}', [DescuentoController::class, 'destroy']);   


//RUTAS PROTEGIDAS DE COMBO
Route::post('/combo/mostrarDesactivados', [ComboController::class,'mostrarDesactivados']);
Route::resource('/combo',ComboController::class) ->except(['store', 'update']);
Route::post('/combo/filtro', [ComboController::class,'filtro']);
Route::post('/combo/buscar', [ComboController::class,'buscar']);
//ruta de Combo con middleware de permiso
Route::middleware('checkPermiso:crear combo')->post('/combo', [ComboController::class, 'store']);
Route::middleware('checkPermiso:crear combo')->put('/combo/{id}', [ComboController::class, 'update']);
Route::middleware('checkPermiso:crear combo')->post('/combo/{id}/desactivar', [ComboController::class, 'desactivar']);
Route::middleware('checkPermiso:crear combo')->post('/combo/{id}/activar', [ComboController::class, 'activar']);

//RUTAS PROTEGIDAS DE ROL
Route::resource('/rol',RolController::class) ->except(['store', 'update', 'destroy']);
//ruta de Rol con middleware de rol
Route::middleware('checkPermiso:crear rol')->post('/rol', [RolController::class, 'store']);
Route::middleware('checkPermiso:crear rol')->put('/rol/{id}', [RolController::class, 'update']);
Route::middleware('checkPermiso:crear rol')->delete('/rol/{id}', [RolController::class, 'destroy']);

//RUTAS PROTEGIDAS DE PERMISO
Route::resource('/permiso',PermisoController::class) ->except(['store', 'update', 'destroy']);
//ruta de Permiso con middleware de permiso 
Route::middleware('checkPermiso:agregar permisos')->post('/permiso', [PermisoController::class, 'store']);
Route::middleware('checkPermiso:agregar permisos')->put('/permiso/{id}', [PermisoController::class, 'update']);
Route::middleware('checkPermiso:agregar permisos')->delete('/permiso/{id}', [PermisoController::class, 'destroy']);
Route::middleware('checkPermiso:agregar permisos')->post('/permiso/vincularPermisoaRol', [PermisoController::class,'vincularPermisoaRol']);

//RUTAS PROTEGIDAS DE STOCK
Route::resource('/stock',StockController::class);;






