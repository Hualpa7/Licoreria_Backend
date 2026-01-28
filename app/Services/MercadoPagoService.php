<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class MercadoPagoService
{
    private string $accessToken;
    private string $baseUrl = 'https://api.mercadopago.com';

    public function __construct()
    {
        $this->accessToken = env('MERCADOPAGO_ACCESS_TOKEN');
    }

    /**
     * Crear una preferencia de pago
     */
    public function createPreference(array $preferenceData)
    {
        try {
            Log::info("🔄 Creando preferencia de pago");
            
            $response = Http::withToken($this->accessToken)
                ->post("{$this->baseUrl}/checkout/preferences", $preferenceData);

            if (!$response->successful()) {
                Log::error("❌ Error creando preferencia", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new Exception("Error al crear preferencia: " . $response->body());
            }

            $preference = $response->json();
            
            Log::info("✅ Preferencia creada exitosamente", [
                'id' => $preference['id'] ?? null
            ]);

            return (object) $preference;
        } catch (Exception $e) {
            Log::error("❌ Exception en createPreference", [
                'error' => $e->getMessage()
            ]);
            throw new Exception('Error al crear la preferencia: ' . $e->getMessage());
        }
    }

    /**
     * Obtener detalles de un pago
     */
    public function getPayment($paymentId)
    {
        try {
            Log::info("📡 Consultando payment: $paymentId");

            $response = Http::withToken($this->accessToken)
                ->get("{$this->baseUrl}/v1/payments/{$paymentId}");

            if (!$response->successful()) {
                Log::error("❌ Error obteniendo payment", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new Exception("Error al obtener payment: " . $response->body());
            }

            $payment = $response->json();

            Log::info("✅ Payment obtenido exitosamente", [
                'id' => $payment['id'] ?? null,
                'status' => $payment['status'] ?? null
            ]);

            return (object) $payment;
        } catch (Exception $e) {
            Log::error("❌ Error en getPayment", [
                'paymentId' => $paymentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener una orden de merchant
     */
    public function getMerchantOrder($orderId)
    {
        try {
            Log::info("📡 Consultando merchant_order: $orderId");

            $response = Http::withToken($this->accessToken)
                ->get("{$this->baseUrl}/merchant_orders/{$orderId}");

            if (!$response->successful()) {
                Log::error("❌ Error obteniendo merchant_order", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new Exception("Error al obtener merchant_order: " . $response->body());
            }

            $order = $response->json();

            Log::info("✅ Merchant Order obtenida exitosamente", [
                'id' => $order['id'] ?? null
            ]);

            return (object) $order;
        } catch (Exception $e) {
            Log::error("❌ Error en getMerchantOrder", [
                'orderId' => $orderId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}