<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\Producto;
use App\Models\Proveedor;
use Exception;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductoController extends Controller
{

  public function index()
  {
    //  return Producto::all(); // Devuelve todos los productos
    /*
      $productos = DB::table('producto')
          -> leftJoin('stock','producto.id_producto','=','stock.id_producto')
          -> select(
            'producto.*',
            DB::raw('COALESCE(SUM(stock.cantidad), 0) as stock')
          )
          ->groupBy('producto.id_producto')
          ->get();
          return response()->json($productos);*/
    $productos = Producto::leftJoin('stock', 'producto.id_producto', '=', 'stock.id_producto')
      ->select(
        'producto.*',
        DB::raw('COALESCE(SUM(stock.cantidad), 0) as stock')
      )
      ->groupBy('producto.id_producto')
      ->get();

    return $productos;
  }



  public function store(StoreProductoRequest $request)
  {
    $datosValidos = $request->validated();

    // SI LLEGA FOTO, LA GUARDAMOS EN storage/app/public/productos
    if ($request->hasFile('foto')) {
      $ruta = $request->file('foto')->store('productos', 'public');
      $datosValidos['foto'] = $ruta; // Guardar SOLO la ruta en BD
    }

    $producto = Producto::create($datosValidos);

    return response()->json($producto, 201);
  }



  public function show($id)
  {
    /* SI HAGO LA RELACIONES DE MODELOS LA CONSULTA QUEDARIA MUCHO MAS SENCILLA:*/
    // Carga categoría y marca como antes,
    // pero AHORA carga la relación de descuentos SIN filtrar por sucursal
    $producto = Producto::with(['categoria', 'marca', 'descuentos']) //tablas categoria y marca y descuntos
      ->withSum('stock as stock', 'cantidad') //se trata de sumar los valores de cantidad de la tabla stock
      ->findOrFail($id); // Encuentra el producto o lanza un error si no existe

    $producto->stock = $producto->stock ?? 0; //si no hay registros con ese id_prdcuto se retorna 0

    // Convertir ruta en URL pública
    if ($producto->foto) {
      $producto->foto_url = Storage::url($producto->foto);
    }

    return $producto;
  }


  public function mostrar2($id, Request $request)
  {
    try {
      $usuario = JWTAuth::parseToken()->authenticate();

      if ($usuario->id_rol != 5) {
        $idSucursal = $usuario->id_sucursal;
      } else {
        $request->validate([
          'id_sucursal' => 'required|exists:sucursal,id_sucursal'
        ]);
        $idSucursal = $request->id_sucursal;
      }

      $producto = Producto::with([
        'categoria',
        'marca',
        'descuentos' => function ($q) use ($idSucursal) {
          $q->wherePivot('id_sucursal', $idSucursal);
        }
      ])
        ->withSum(['stock as stock' => function ($query) use ($idSucursal) {
          $query->where('id_sucursal', $idSucursal);
        }], 'cantidad')
        ->findOrFail($id);

      $producto->stock = $producto->stock ?? 0; //si no hay registros de stock, retorna 0

      // URL pública de la foto
      if ($producto->foto) {
        $producto->foto_url = Storage::url($producto->foto);
      }

      // Convertimos "descuentos" en "descuento"
      $producto->descuento = $producto->descuentos->first() ?? null;

      unset($producto->descuentos);

      return $producto;
    } catch (\Exception $e) {
      return response()->json([
        'error' => 'Error al filtrar productos.',
        'detalle' => $e->getMessage()
      ], 500);
    }
  }





  public function update(UpdateProductoRequest $request, string $id)
  { //SE HACEN LAS VALIDACIONES DE UNIQUE DIRECTAMENTE EN LA BD AQUI

    $producto = Producto::findOrFail($id);

    if (Producto::where('codigo', $request->codigo)->where('id_producto', '!=', $id)->exists()) {
      return response()->json([
        'message' => 'El código ya está en uso por otro producto.',
      ], 422);
    }

    if (Producto::where('producto', $request->producto)->where('id_producto', '!=', $id)->exists()) {
      return response()->json([
        'message' => 'El nombre del producto ya está en uso por otro producto.',
      ], 422);
    }

    $datosValidos = $request->only(['codigo', 'producto', 'alerta_minima', 'costo', 'id_categoria', 'id_marca']);

    // Manejo de eliminación de imagen
    if ($request->has('eliminar_foto')) {
      if ($producto->foto && Storage::disk('public')->exists($producto->foto)) {
        Storage::disk('public')->delete($producto->foto);
      }
      $datosValidos['foto'] = null;
    }
    // Manejo de nueva imagen
    else if ($request->hasFile('foto')) {
      // Borrar foto anterior si existe
      if ($producto->foto && Storage::disk('public')->exists($producto->foto)) {
        Storage::disk('public')->delete($producto->foto);
      }
      $ruta = $request->file('foto')->store('productos', 'public');
      $datosValidos['foto'] = $ruta;
    }

    $producto->update($datosValidos);

    return response()->json([
      'message' => 'Producto actualizado exitosamente',
      'producto' => $producto
    ], 200);
  }



  public function destroy(string $id)
  {
    return "destroy";
  }

  public function filtro(Request $request)
  {
    try {
      //Autenticar usuario desde el token
      $usuario = JWTAuth::parseToken()->authenticate();

      // Si NO es superadmin (id_rol <> 5), usa la sucursal del token
      // Si es superadmin, valida que haya una sucursal recibida en el request
      if ($usuario->id_rol != 5) {
        $idSucursal = $usuario->id_sucursal;
      } else {
        $request->validate([
          'id_sucursal' => 'required|exists:sucursal,id_sucursal'
        ]);
        $idSucursal = $request->id_sucursal;
      }

      // Construir la consulta base
      // NOTE: cargamos la relación 'descuentos' filtrada por la sucursal (debería traer 0 o 1)
      $query = $query = Producto::select([
        'id_producto',
        'codigo',
        'producto',
        'fecha_modificacion',
        'alerta_minima',
        'es_combo',
        'activo',
        'costo',
        'id_categoria',
        'id_marca'
      ])
        ->with(['descuentos' => function ($q) use ($idSucursal) {
          $q->wherePivot('id_sucursal', $idSucursal);
        }])
        ->withSum(['stock' => function ($subQuery) use ($idSucursal) {
          // Filtra stock solo por la sucursal correspondiente
          $subQuery->where('id_sucursal', $idSucursal);
        }], 'cantidad');

      //  Aplicar filtros opcionales
      if ($request->id_marca != null)
        $query->where('id_marca', $request->id_marca);

      if ($request->id_categoria != null)
        $query->where('id_categoria', $request->id_categoria);

      if ($request->busqueda && $request->tipo === "Nombre")
        $query->whereRaw('LOWER(producto) LIKE ?', ['%' . strtolower($request->busqueda) . '%']);

      if ($request->busqueda && $request->tipo === "Codigo")
        $query->whereRaw('LOWER(codigo) LIKE ?', ['%' . strtolower($request->busqueda) . '%']);

      // Obtener resultados
      $productos = $query->get();

      // Normalizar/transformar los productos para frontend:
      // - stock (compatibilidad)
      // - descuento (traemos el primer descuento para la sucursal, o null)
      $productos = $productos->map(function ($producto) {
        // Stock: compatibilidad con frontend (lo mismo que hacías)
        $producto->stock = $producto->stock_sum_cantidad ?? 0;
        unset($producto->stock_sum_cantidad);

        // Descuento: si la relación 'descuentos' trae algo (debería ser 0 o 1), lo asignamos a 'descuento'
        $producto->descuento = $producto->descuentos->first() ?? null;

        // Eliminamos la colección 'descuentos' para no duplicar datos
        unset($producto->descuentos);

        // URL pública de la imagen
        if ($producto->foto) {
          $producto->foto_url = Storage::url($producto->foto);
        }

        return $producto;
      });

      // Garantizamos devolver un array (collection->values()->all() devuelve array-indexed)
      return response()->json($productos->values()->all());
    } catch (\Exception $e) {
      return response()->json([
        'error' => 'Error al filtrar productos.',
        'detalle' => $e->getMessage()
      ], 500);
    }
  }

  public function buscar(Request $request)
  {
    $termino = $request->termino;
    $tipoBusqueda = $request->tipoBusquedaProducto;


    if ($tipoBusqueda != null && $tipoBusqueda === 'Nombre') {
      $resultados = Producto::whereRaw('producto LIKE ?', ['%' . strtolower($termino) . '%'])
        ->get();
    }
    if ($tipoBusqueda != null && $tipoBusqueda === 'Codigo') {
      $resultados = Producto::whereRaw('LOWER(codigo) LIKE ?', ['%' . strtolower($termino) . '%'])
        ->get();
    }


    return response()->json($resultados);
  }

  public function transferir(Request $request)
  {
    $request->validate([
      'id_producto' => 'required|integer|exists:producto,id_producto',
      'cantidad' => 'required|integer|min:1',
      'sucursalOrigen' => 'required|integer|exists:sucursal,id_sucursal',
      'sucursalDestino' => 'required|integer|exists:sucursal,id_sucursal|different:sucursalOrigen',
    ]);

    try {
      DB::transaction(function () use ($request) {


        // Verificamos stock disponible en la sucursal origen
        $stockOrigen = DB::table('stock')
          ->where('id_producto', $request->id_producto)
          ->where('id_sucursal', $request->sucursalOrigen)
          ->sum('cantidad');


        if ($stockOrigen < $request->cantidad) {
          throw new Exception("No hay stock suficiente del producto en la sucursal de origen.");
        }

        // Creamos un identificador único de transferencia
        $idTransferencia = DB::table('stock')->max('id_transferencia') + 1;

        // Restamos stock en sucursal origen (salida)
        DB::table('stock')->insert([
          'cantidad' => -$request->cantidad,
          'tipo' => 'Transferencia',
          'observaciones' => 'Salida a sucursal ' . $request->sucursalDestino,
          'id_producto' => $request->id_producto,
          'id_sucursal' => $request->sucursalOrigen,
          'id_transferencia' => $idTransferencia,
        ]);

        // Sumamos stock en sucursal destino (entrada)
        DB::table('stock')->insert([
          'cantidad' => $request->cantidad,
          'tipo' => 'Transferencia',
          'observaciones' => 'Entrada desde sucursal ' . $request->sucursalOrigen,
          'id_producto' => $request->id_producto,
          'id_sucursal' => $request->sucursalDestino,
          'id_transferencia' => $idTransferencia,
        ]);
      });

      return response()->json(['message' => 'Transferencia realizada correctamente.'], 201);
    } catch (Exception $e) {
      return response()->json([
        'error' => 'Error al realizar la transferencia.',
        'detalle' => $e->getMessage()
      ], 400);
    }
  }


  //FUNCION PARA TRAER RODCUTOS CON DESCUENTOS Y MOSTRARLOS EN EL INICIO

  public function productosConDescuentos()
  {
    try {
      // Obtener todos los productos que tengan descuentos
      // La consulta trae TODAS las combinaciones producto-descuento-sucursal
      $productosConDescuentos = Producto::query()
        ->join('producto_descuento', 'producto.id_producto', '=', 'producto_descuento.id_producto')
        ->join('descuento', 'producto_descuento.id_descuento', '=', 'descuento.id_descuento')
        ->join('sucursal', 'producto_descuento.id_sucursal', '=', 'sucursal.id_sucursal')
        ->select([
          'producto.producto as nombre',
          'producto.costo as precioOriginal',
          'producto.foto as imagen',
          'descuento.porcentaje as descuentoPorcentaje',
          'sucursal.nombre as sucursal',
          'producto_descuento.id_sucursal'
        ])
        ->get()
        ->map(function ($item) {
          // Convertir foto a URL pública
          if ($item->foto) {
            $item->foto_url = Storage::url($item->foto);
            unset($item->foto); // Eliminamos la ruta guardada, dejamos solo la URL
          }

          // Convertir costo a número para cálculos en frontend
          $item->costo = (float)str_replace(',', '.', $item->costo);

          // Eliminamos id_sucursal de pivot, ya que está en 'sucursal'
          unset($item->id_sucursal);

          return $item;
        });

      return response()->json($productosConDescuentos);
    } catch (\Exception $e) {
      return response()->json([
        'error' => 'Error al obtener productos con descuentos.',
        'detalle' => $e->getMessage()
      ], 500);
    }
  }
}
