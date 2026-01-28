<?php

namespace App\Services;

use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use Exception;
use Illuminate\Support\Facades\Log;

class MercadoPagoService
{
    private PreferenceClient $preferenceClient;
    private PaymentClient $paymentClient;

    public function __construct()
    {
        // Configura el access token desde tu .env
        MercadoPagoConfig::setAccessToken(env('MERCADOPAGO_ACCESS_TOKEN'));
        $this->preferenceClient = new PreferenceClient();
        $this->paymentClient = new PaymentClient();
    }

    /**
     * Crear una preferencia de pago
     */
    public function createPreference(array $preferenceData)
    {
        try {
            $preference = $this->preferenceClient->create($preferenceData);
            return $preference;
        } catch (Exception $e) {
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
            $payment = $this->paymentClient->get($paymentId);
            Log::info("✅ Payment obtenido exitosamente", [
                'id' => $payment->id,
                'status' => $payment->status
            ]);
            return $payment;
        } catch (Exception $e) {
            Log::error("❌ Error en getPayment", [
                'paymentId' => $paymentId,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw $e;
        }
    }
}
