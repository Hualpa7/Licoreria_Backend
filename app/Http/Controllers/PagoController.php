<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\MercadoPagoService;
use App\Models\Venta;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class PagoController extends Controller
{
    private MercadoPagoService $mercadoPagoService;

    public function __construct(MercadoPagoService $mercadoPagoService)
    {
        $this->mercadoPagoService = $mercadoPagoService;
    }

    /**
     * Crear la preferencia de pago para mp
     * se utiliza cuando el usuario elige pagar por transferencia desde el fronent
     * guarda temporalmente la venta en ventas_pendientes
     */
    public function crearPreferenciaPago(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'title' => 'required|string',
                'quantity' => 'required|integer|min:1',
                'price' => 'required|numeric|min:0.01',
                'datos_venta' => 'required|array',
            ]);

            Log::info('CrearPreferenciaPago: Datos recibidos', [
                'venta' => $request->datos_venta
            ]);

            $ventaPendienteJson = json_encode($request->datos_venta);
            $externalReference = 'venta_' . uniqid();

            DB::table('ventas_pendientes')->insert([
                'external_reference' => $externalReference,
                'datos_venta' => $ventaPendienteJson,
                'procesada' => false,
                'created_at' => now(),
            ]);

            Log::info('CrearPreferenciaPago: Venta pendiente almacenada', [
                'external_reference' => $externalReference
            ]);

            $baseUrl = env('NGROK_URL') ?: env('APP_URL');
            
            $preferenceData = [
                "items" => [
                    [
                        "title" => $request->title,
                        "quantity" => (int)$request->quantity,
                        "unit_price" => (float)$request->price,
                        "currency_id" => "ARS",
                    ]
                ],
                "back_urls" => [
                    "success" => "$baseUrl/api/pagos/success",
                    "failure" => "$baseUrl/api/pagos/failure",
                    "pending" => "$baseUrl/api/pagos/pending"
                ],
                "auto_return" => "approved",
                "external_reference" => $externalReference,
                "notification_url" => "$baseUrl/api/pagos/webhook",
            ];

            $preference = $this->mercadoPagoService->createPreference($preferenceData);

            Log::info('CrearPreferenciaPago: Preferencia creada', [
                'id' => $preference->id
            ]);

            return response()->json([
                'id' => $preference->id,
                'init_point' => $preference->init_point,
                'sandbox_init_point' => $preference->sandbox_init_point
            ], 200);
        } catch (Exception $error) {
            Log::error('CrearPreferenciaPago: Error', [
                'detalle' => $error->getMessage()
            ]);

            return response()->json([
                'error' => 'Error al crear la preferencia de pago',
                'detalle' => $error->getMessage()
            ], 500);
        }
    }

    /**
     * Webhook que recibe notificaciones automáticas de mp, o sea cuando hago alguna transaccion
     * necestio recibir si el evento de pago u orden de pago fue recibido para 
     * registrar la venta y descontar stock
     */
    public function webhookMercadoPago(Request $request): JsonResponse
    {
        Log::info("Webhook recibido", [
            'body' => $request->all()
        ]);

        try {
            if (isset($request['topic']) && $request['topic'] === 'merchant_order') {
                return $this->handleMerchantOrderWebhook($request);
            }

            if (isset($request['type']) && $request['type'] === 'payment') {
                return $this->handlePaymentWebhook($request);
            }

            Log::warning("Webhook desconocido", [
                'data' => $request->all()
            ]);

            return response()->json(['status' => 'unknown type'], 200);
        } catch (Exception $e) {
            Log::error("Error webhook", [
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => $e->getMessage()], 200);
        }
    }

    /**
     * Maneja notificaciones de tipo merchant_order
     * se usa cuando MP manda primero la orden del checkout
     */
    private function handleMerchantOrderWebhook(Request $request): JsonResponse
    {
        Log::info("Procesando webhook merchant_order");

        try {
            $resourceUrl = $request['resource'] ?? null;
            if (!$resourceUrl) {
                Log::warning("merchant_order sin resource URL");
                return response()->json(['status' => 'no resource'], 400);
            }

            $orderId = $this->extractOrderIdFromUrl($resourceUrl);
            if (!$orderId) {
                Log::warning("merchant_order: no se pudo extraer ID");
                return response()->json(['status' => 'invalid url'], 400);
            }

            // ✅ AHORA USAMOS EL MÉTODO DEL SERVICIO EN LUGAR DE CURL
            $merchantOrder = $this->mercadoPagoService->getMerchantOrder($orderId);

            Log::info("merchant_order obtenida", [
                'id' => $merchantOrder->id
            ]);

            foreach ($merchantOrder->payments ?? [] as $paymentInfo) {
                $paymentId = is_array($paymentInfo) ? $paymentInfo['id'] : $paymentInfo;

                try {
                    $payment = $this->mercadoPagoService->getPayment($paymentId);

                    if ($payment->status === 'approved') {
                        Log::info("Pago aprobado encontrado", [
                            'payment_id' => $payment->id
                        ]);
                        return $this->processApprovedPayment($payment);
                    }
                } catch (Exception $e) {
                    Log::warning("Error consultando pago $paymentId", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info("merchant_order sin pagos aprobados");
            return response()->json(['status' => 'no approved payments'], 200);
        } catch (Exception $e) {
            Log::error("Error procesando merchant_order", [
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => $e->getMessage()], 200);
        }
    }

    /**
     * Maneja webhook de tipo payment
     * MP lo envía cuando se confirma un pago individua
     */
    private function handlePaymentWebhook(Request $request): JsonResponse
    {
        if (!isset($request['data']['id'])) {
            Log::warning("Webhook payment sin ID");
            return response()->json(['status' => 'no id'], 400);
        }

        $paymentId = $request['data']['id'];
        Log::info("Consultando payment", ['payment_id' => $paymentId]);

        try {
            $payment = $this->mercadoPagoService->getPayment($paymentId);
        } catch (Exception $e) {
            Log::warning("Error obteniendo payment", [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
            return response()->json(['status' => 'payment api error'], 200);
        }

        if ($payment->status !== 'approved') {
            return response()->json(['status' => 'not approved'], 200);
        }

        return $this->processApprovedPayment($payment);
    }

    /**
     * Si la venta ue aprobad, se la procesa
     * Toma la venta pendiente, la crea en la BD y descuenta stock
     */
    private function processApprovedPayment($payment): JsonResponse
    {
        $externalRef = $payment->external_reference ?? null;

        if (!$externalRef) {
            Log::error("Pago sin external_reference");
            return response()->json(['error' => 'no reference'], 400);
        }

        $ventaPendiente = DB::table('ventas_pendientes')
            ->where('external_reference', $externalRef)
            ->where('procesada', false)
            ->first();

        if (!$ventaPendiente) {
            Log::error("No existe venta pendiente para referencia", [
                'ref' => $externalRef
            ]);
            return response()->json(['error' => 'no pending sale'], 404);
        }

        $datosVenta = json_decode($ventaPendiente->datos_venta, true);

        $this->procesarVenta($datosVenta);

        DB::table('ventas_pendientes')
            ->where('external_reference', $externalRef)
            ->update([
                'procesada' => true,
                'processed_at' => now()
            ]);

        Log::info("Venta procesada correctamente", [
            'external_reference' => $externalRef,
            'payment_id' => $payment->id
        ]);

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Extrae ID numérico desde la URL de merchant_order.
     */
    private function extractOrderIdFromUrl($url): ?string
    {
        $parts = explode('/', $url);
        return end($parts) ?: null;
    }

    /**
     * Procesa una venta final:
     * Crea la venta -> Inserta venta_producto o venta_combo -> descuenta stock sin validacion
     * ya que la validaicon la hago en el frontend antes de abrir la pagina de mp para el pago
     */
    private function procesarVenta(array $datosVenta)
    {
        DB::transaction(function () use ($datosVenta) {

            $venta = Venta::create([
                'id_usuario' => $datosVenta['id_usuario'],
                'id_sucursal' => $datosVenta['id_sucursal'],
                'total' => $datosVenta['total'],
                'total_con_descuento' => $datosVenta['total_con_descuento'],
                'descuento_gral' => $datosVenta['descuento_gral'],
                'metodo_pago' => $datosVenta['metodo_pago'],
            ]);

            foreach ($datosVenta['productos'] as $item) {
                $cantidad = $this->extraerCantidad($item['Cantidad']);

                // Combos
                if (isset($item['esCombo']) && $item['esCombo']) {

                    $combo = DB::table('combo')
                        ->where('combo.id_combo', $item['id_combo'])
                        ->leftJoin('combo_producto', 'combo.id_combo', '=', 'combo_producto.id_combo')
                        ->leftJoin('producto', 'combo_producto.id_producto', '=', 'producto.id_producto')
                        ->select(DB::raw('json_agg(json_build_object(
                            \'producto\', producto.producto,
                            \'id_producto\', producto.id_producto,
                            \'cantidad\', combo_producto.cantidad
                        )) as productos'))
                        ->groupBy('combo.id_combo')
                        ->first();

                    DB::table('venta_combo')->insert([
                        'id_venta' => $venta->id_venta,
                        'id_combo' => $item['id_combo'],
                        'cantidad' => $cantidad,
                    ]);

                    foreach (json_decode($combo->productos) as $prod) {
                        DB::table('stock')->insert([
                            'cantidad' => -$prod->cantidad * $cantidad,
                            'tipo' => "Venta",
                            'id_producto' => $prod->id_producto,
                            'id_venta' => $venta->id_venta,
                            'id_sucursal' => $venta->id_sucursal
                        ]);
                    }
                } else {
                    // Productos normales
                    $descuento = DB::table('producto_descuento as pd')
                        ->join('descuento as d', 'pd.id_descuento', '=', 'd.id_descuento')
                        ->where('pd.id_producto', $item['id_producto'])
                        ->where('pd.id_sucursal', $datosVenta['id_sucursal'])
                        ->value('d.porcentaje');

                    DB::table('venta_producto')->insert([
                        'id_venta' => $venta->id_venta,
                        'id_producto' => $item['id_producto'],
                        'cantidad' => $cantidad,
                        'iva' => $item['IVA'] ?? 0,
                        'descuento' => $descuento ?? 0
                    ]);

                    DB::table('stock')->insert([
                        'cantidad' => -$cantidad,
                        'tipo' => "Venta",
                        'id_producto' => $item['id_producto'],
                        'id_venta' => $venta->id_venta,
                        'id_sucursal' => $venta->id_sucursal
                    ]);
                }
            }
        });
    }

    /*FUNCIONES extras usadas para responder y para corroborar y validar datos como pprecio
    * y cantidad, necesario que se mande correctmante, para procesar bien en MP
    */ 
    private function extraerCantidad($cantidadInput)
    {
        if (is_numeric($cantidadInput)) {
            return (int)$cantidadInput;
        }

        $cantidad = preg_replace('/[^0-9.]/', '', $cantidadInput);
        return $cantidad ? (int)$cantidad : 1;
    }

    public function pagoExitoso(Request $request)
    {
        return response()->json(['status' => 'success']);
    }

    public function pagoFallido(Request $request)
    {
        return response()->json(['status' => 'failure']);
    }

    public function pagoPendiente(Request $request)
    {
        return response()->json(['status' => 'pending']);
    }
}