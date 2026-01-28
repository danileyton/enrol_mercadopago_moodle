<?php
/**
 * AJAX endpoint para validar cupones y actualizar preferencia de pago.
 * 
 * CORRECCIÓN CRÍTICA (BUG-001):
 * - Actualiza el registro existente en BD (no crea nuevo)
 * - Guarda información del cupón en el registro
 * - Actualiza preference_id con la nueva preferencia
 * 
 * @package    enrol_mercadopago
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/classes/util.php');
require_once(__DIR__ . '/classes/coupon_manager.php');

use enrol_mercadopago\util;
use enrol_mercadopago\coupon_manager;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;

require_login();

global $DB, $USER, $CFG;

header('Content-Type: application/json; charset=utf-8');

// =========================================================================
// Parámetros requeridos
// =========================================================================
$courseid   = required_param('courseid', PARAM_INT);
$instanceid = required_param('instanceid', PARAM_INT);
$recordid   = optional_param('recordid', 0, PARAM_INT);
$code       = optional_param('code', '', PARAM_TEXT);

// Validar que existan curso e instancia
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$instance = $DB->get_record('enrol', ['id' => $instanceid, 'enrol' => 'mercadopago'], '*', MUST_EXIST);

// =========================================================================
// Buscar el registro de pago existente
// =========================================================================
$paymentrecord = null;

if ($recordid > 0) {
    $paymentrecord = $DB->get_record('enrol_mercadopago', [
        'id' => $recordid,
        'userid' => $USER->id,
        'courseid' => $courseid
    ]);
}

// Si no se encuentra por ID, buscar por usuario/curso/instancia en estado initiated
if (!$paymentrecord) {
    $paymentrecord = $DB->get_record('enrol_mercadopago', [
        'courseid' => $courseid,
        'userid' => $USER->id,
        'instanceid' => $instanceid,
        'status' => 'initiated'
    ]);
}

if (!$paymentrecord) {
    util::log("⚠️ validate_coupon: No se encontró registro de pago para user={$USER->id}, course={$courseid}", 'warning');
    echo json_encode([
        'valid' => false, 
        'message' => 'No se encontró un proceso de pago activo. Recarga la página e intenta nuevamente.'
    ]);
    exit;
}

// =========================================================================
// Configurar Mercado Pago
// =========================================================================
$token     = get_config('enrol_mercadopago', 'accesstoken');
$publickey = get_config('enrol_mercadopago', 'publickey');

if (empty($token) || empty($publickey)) {
    echo json_encode(['valid' => false, 'message' => 'Error de configuración del sistema.']);
    exit;
}

MercadoPagoConfig::setAccessToken($token);

$originalcost = (float)$instance->cost;
$finalcost    = $originalcost;
$discount     = 0.0;
$successmsg   = '';
$currency     = $instance->currency ?? 'CLP';
$couponid     = null;
$couponcode   = null;

// =========================================================================
// Validar cupón si se proporcionó
// =========================================================================
if (!empty($code)) {
    $code = strtoupper(trim($code));
    
    // Validar cupón básico (existe, activo, fechas, límite global)
    $coupon = coupon_manager::validate_coupon($courseid, $code);
    
    if (!$coupon) {
        echo json_encode(['valid' => false, 'message' => '❌ Cupón no válido o inactivo.']);
        exit;
    }
    
    $now = time();
    
    // Verificar fechas
    if (!empty($coupon->validfrom) && $now < $coupon->validfrom) {
        echo json_encode([
            'valid' => false, 
            'message' => '⏳ Este cupón será válido a partir del ' . date('d/m/Y', $coupon->validfrom)
        ]);
        exit;
    }
    
    if (!empty($coupon->validuntil) && $now > $coupon->validuntil) {
        echo json_encode([
            'valid' => false, 
            'message' => '⏰ Este cupón expiró el ' . date('d/m/Y', $coupon->validuntil)
        ]);
        exit;
    }
    
    // Verificar límite de usos globales
    if ($coupon->maxuses > 0 && $coupon->usedcount >= $coupon->maxuses) {
        echo json_encode([
            'valid' => false, 
            'message' => '🎫 Este cupón ha alcanzado su límite de usos.'
        ]);
        exit;
    }
    
    // Verificar si el usuario ya usó este cupón
    $previoususe = $DB->get_record('enrol_mercadopago_coupon_usage', [
        'couponid' => $coupon->id,
        'userid' => $USER->id
    ]);
    
    if ($previoususe) {
        echo json_encode([
            'valid' => false, 
            'message' => '⚠️ Ya has utilizado este cupón anteriormente.'
        ]);
        exit;
    }
    
    // Verificar elegibilidad por cohorte si es cupón restringido
    if ($coupon->eligibility_type === 'restricted') {
        $eligible = coupon_manager::check_cohort_eligibility($coupon->id, $USER->id);
        if (!$eligible) {
            echo json_encode([
                'valid' => false, 
                'message' => '🚫 No tienes acceso a este cupón. Contacta a soporte si crees que deberías poder usarlo.'
            ]);
            exit;
        }
    }
    
    // =====================================================================
    // Calcular descuento
    // =====================================================================
    if ($coupon->type === 'percent') {
        $discount = round($originalcost * ($coupon->value / 100), 2);
        $finalcost = max(0, $originalcost - $discount);
        $successmsg = "✅ Cupón aplicado: {$coupon->value}% de descuento.";
    } else {
        $discount = (float)$coupon->value;
        $finalcost = max(0, $originalcost - $discount);
        $successmsg = "✅ Cupón aplicado: $" . number_format($discount, 0, ',', '.') . " de descuento.";
    }
    
    $couponid = $coupon->id;
    $couponcode = $coupon->code;
    
    util::log("🎟️ Cupón validado: {$code} para user={$USER->id}, descuento={$discount}, final={$finalcost}");
}

// =========================================================================
// Crear nueva preferencia en Mercado Pago con el monto actualizado
// =========================================================================
try {
    // Generar nueva external_reference
    $externalref = sprintf('%d-%d-%d-%d', $courseid, $USER->id, $instanceid, time());
    
    $client = new PreferenceClient();
    $preference = $client->create([
        "items" => [[
            "title" => format_string($course->fullname),
            "quantity" => 1,
            "unit_price" => (float)$finalcost,
            "currency_id" => $currency
        ]],
        "payer" => [
            "email" => $USER->email,
            "name" => $USER->firstname,
            "surname" => $USER->lastname
        ],
        "external_reference" => $externalref,
        "metadata" => [
            "userid" => $USER->id,
            "courseid" => $courseid,
            "instanceid" => $instanceid,
            "payment_record_id" => $paymentrecord->id,
            "coupon" => $couponcode,
            "discount" => $discount,
            "original_amount" => $originalcost
        ],
        "back_urls" => [
            "success" => $CFG->wwwroot . "/enrol/mercadopago/return.php?status=success&courseid={$courseid}&instanceid={$instanceid}&userid={$USER->id}",
            "failure" => $CFG->wwwroot . "/enrol/mercadopago/return.php?status=failure&courseid={$courseid}&instanceid={$instanceid}&userid={$USER->id}",
            "pending" => $CFG->wwwroot . "/enrol/mercadopago/return.php?status=pending&courseid={$courseid}&instanceid={$instanceid}&userid={$USER->id}"
        ],
        "notification_url" => $CFG->wwwroot . "/enrol/mercadopago/ipn.php",
        "auto_return" => "approved"
    ]);

    // =====================================================================
    // CRÍTICO: Actualizar el registro existente con la nueva información
    // =====================================================================
    $paymentrecord->preference_id = $preference->id;
    $paymentrecord->external_reference = $externalref;
    $paymentrecord->amount = $originalcost;
    $paymentrecord->discount = $discount;
    $paymentrecord->final_amount = $finalcost;
    $paymentrecord->couponcode = $couponcode;
    $paymentrecord->couponid = $couponid;
    $paymentrecord->timemodified = time();
    
    $DB->update_record('enrol_mercadopago', $paymentrecord);
    
    util::log("🔄 Registro actualizado con cupón (id={$paymentrecord->id}, preference={$preference->id}, coupon={$couponcode})");

    echo json_encode([
        'valid' => true,
        'message' => $successmsg,
        'amount' => $finalcost,
        'original_amount' => $originalcost,
        'discount' => $discount,
        'currency' => $currency,
        'preference_id' => $preference->id,
        'public_key' => $publickey
    ]);
    
} catch (\Exception $e) {
    util::log("❌ Error creando preferencia AJAX: " . $e->getMessage(), 'error');
    echo json_encode([
        'valid' => false, 
        'message' => 'Error al procesar el cupón. Intenta nuevamente.'
    ]);
}

exit;
