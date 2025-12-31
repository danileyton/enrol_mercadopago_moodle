<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/classes/util.php');

use enrol_mercadopago\util;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;

require_login();

global $DB, $USER, $CFG;

header('Content-Type: application/json; charset=utf-8');

$courseid = required_param('courseid', PARAM_INT);
$code = optional_param('code', '', PARAM_TEXT);
$instanceid = required_param('instanceid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$instance = $DB->get_record('enrol', ['id' => $instanceid, 'enrol' => 'mercadopago'], '*', MUST_EXIST);

$token     = get_config('enrol_mercadopago', 'accesstoken');
$publickey = get_config('enrol_mercadopago', 'publickey');
MercadoPagoConfig::setAccessToken($token);

$originalcost = (float)$instance->cost;
$finalcost = $originalcost;
$discount = 0.0;
$successmsg = '';
$currency = $instance->currency ?? 'CLP';
$today = time();

if (!empty($code)) {
    $coupon = $DB->get_record('enrol_mercadopago_coupons', [
        'courseid' => $courseid,
        'code' => strtoupper(trim($code)),
        'active' => 1
    ]);

    if ($coupon) {
        if (!empty($coupon->validfrom) && $today < $coupon->validfrom) {
            echo json_encode(['valid' => false, 'message' => '⏳ Cupón válido desde ' . date('d/m/Y', $coupon->validfrom)]);
            exit;
        }
        if (!empty($coupon->validuntil) && $today > $coupon->validuntil) {
            echo json_encode(['valid' => false, 'message' => '⏰ Cupón expirado el ' . date('d/m/Y', $coupon->validuntil)]);
            exit;
        }

        if ($coupon->type === 'percent') {
            $discount = ($originalcost * ($coupon->value / 100));
            $finalcost = max(0, $originalcost - $discount);
            $successmsg = "✅ Cupón aplicado: {$coupon->value}% de descuento.";
        } elseif ($coupon->type === 'amount') {
            $discount = $coupon->value;
            $finalcost = max(0, $originalcost - $discount);
            $successmsg = "✅ Cupón aplicado: $" . number_format($discount, 0, ',', '.') . " de descuento.";
        }
    } else {
        echo json_encode(['valid' => false, 'message' => '❌ Cupón no válido o inactivo.']);
        exit;
    }
}

// --- Crear nueva preferencia en Mercado Pago ---
try {
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
        "metadata" => [
            "userid" => $USER->id,
            "courseid" => $courseid,
            "instanceid" => $instanceid,
            "coupon" => $code,
            "discount" => $discount
        ],
        "back_urls" => [
            "success" => $CFG->wwwroot . "/enrol/mercadopago/return.php?status=success&courseid={$courseid}&instanceid={$instanceid}&userid={$USER->id}",
            "failure" => $CFG->wwwroot . "/enrol/mercadopago/return.php?status=failure&courseid={$courseid}&instanceid={$instanceid}&userid={$USER->id}",
            "pending" => $CFG->wwwroot . "/enrol/mercadopago/return.php?status=pending&courseid={$courseid}&instanceid={$instanceid}&userid={$USER->id}"
        ],
        "notification_url" => $CFG->wwwroot . "/enrol/mercadopago/ipn.php",
        "auto_return" => "approved"
    ]);

    echo json_encode([
        'valid' => true,
        'message' => $successmsg,
        'amount' => $finalcost,
        'currency' => $currency,
        'preference_id' => $preference->id,
        'public_key' => $publickey
    ]);
} catch (Exception $e) {
    util::log("❌ Error creando preferencia AJAX: " . $e->getMessage(), 'error');
    echo json_encode(['valid' => false, 'message' => 'Error al crear preferencia en Mercado Pago.']);
}
exit;
