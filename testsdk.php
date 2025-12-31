<?php
require_once(__DIR__ . '/vendor/autoload.php');

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;

// Configura tu token de prueba
MercadoPagoConfig::setAccessToken('TEST-xxxxxxxxxxxxxxxxxxxxxxxx');

// Instancia un cliente de pago para probar la conexión
$client = new PaymentClient();

echo "✅ SDK v3 cargado correctamente y configurado.<br>";
echo "Clase de cliente: " . get_class($client);
