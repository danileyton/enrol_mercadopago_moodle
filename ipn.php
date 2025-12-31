<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->dirroot . '/enrol/mercadopago/classes/util.php');
require_once($CFG->dirroot . '/enrol/mercadopago/classes/coupon_manager.php'); // 🧩 NUEVO

use enrol_mercadopago\util;
use enrol_mercadopago\coupon_manager;

global $DB;

// -----------------------------------------------------------------------------
// 📩 1. Leer la notificación IPN de Mercado Pago
// -----------------------------------------------------------------------------
$json = file_get_contents('php://input');
$data = json_decode($json, true);

util::log("📩 IPN recibido: " . $json);

if (empty($data['data']['id'])) {
    util::log("⚠️ IPN sin payment ID", 'warning');
    http_response_code(400);
    exit('Missing payment ID');
}

$paymentid = $data['data']['id'];

// -----------------------------------------------------------------------------
// 🔍 2. Buscar el registro de pago correspondiente
// -----------------------------------------------------------------------------
$payment = $DB->get_record('enrol_mercadopago', ['paymentid' => $paymentid]);
if (!$payment) {
    util::log("⚠️ IPN sin coincidencia en la base de datos (paymentid={$paymentid})", 'warning');
    http_response_code(200);
    exit('No match found');
}

// -----------------------------------------------------------------------------
// 🔄 3. Actualizar estado del pago
// -----------------------------------------------------------------------------
$payment->status = 'approved';
$payment->confirmedby = 'IPN';
$payment->timemodified = time();
$DB->update_record('enrol_mercadopago', $payment);

util::log("✅ IPN: estado actualizado a 'approved' para paymentid={$paymentid}");

// -----------------------------------------------------------------------------
// 🎟️ 4. Registrar uso del cupón si existe
// -----------------------------------------------------------------------------
if (!empty($payment->couponcode)) {
    try {
        coupon_manager::register_coupon_use(
            $payment->couponcode,
            $payment->userid,
            $payment->courseid,
            $payment->paymentid
        );
        util::log("🎟️ Cupón '{$payment->couponcode}' registrado correctamente por IPN (user={$payment->userid})");
    } catch (Throwable $e) {
        util::log("❌ Error al registrar uso de cupón vía IPN: " . $e->getMessage(), 'error');
    }
}

// -----------------------------------------------------------------------------
// 🎓 5. Matricular al usuario en el curso (si procede)
// -----------------------------------------------------------------------------
$instance = $DB->get_record('enrol', ['id' => $payment->instanceid, 'enrol' => 'mercadopago']);
if ($instance) {
    try {
        $enrol = enrol_get_plugin('mercadopago');
        $enrol->enrol_user($instance, $payment->userid, $instance->roleid, time());
        util::log("🎓 Usuario matriculado correctamente vía IPN (user={$payment->userid}, course={$payment->courseid})");
    } catch (Throwable $e) {
        util::log("❌ Error al matricular vía IPN: " . $e->getMessage(), 'error');
    }
} else {
    util::log("⚠️ IPN: no se encontró instancia enrol para instanceid={$payment->instanceid}", 'warning');
}

// -----------------------------------------------------------------------------
// ✅ 6. Respuesta a Mercado Pago
// -----------------------------------------------------------------------------
http_response_code(200);
echo 'OK';
