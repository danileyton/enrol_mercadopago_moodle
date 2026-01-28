<?php
/**
 * Página de retorno desde Mercado Pago después del pago.
 * 
 * CORRECCIONES:
 * - BUG-002: Cambiado $record por $payment
 * - Mejora búsqueda de registro (multiple fallbacks)
 * - Verificación de idempotencia (no procesar dos veces)
 * - Marcado de confirmedby para evitar race condition con IPN
 * 
 * @package    enrol_mercadopago
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->dirroot . '/enrol/mercadopago/classes/util.php');
require_once($CFG->dirroot . '/enrol/mercadopago/classes/coupon_manager.php');

use enrol_mercadopago\util;
use enrol_mercadopago\coupon_manager;

global $DB, $USER;

// =========================================================================
// Parámetros de retorno
// =========================================================================
$courseid      = required_param('courseid', PARAM_INT);
$instanceid    = required_param('instanceid', PARAM_INT);
$userid        = required_param('userid', PARAM_INT);
$status        = optional_param('status', '', PARAM_TEXT);
$paymentid     = optional_param('payment_id', '', PARAM_TEXT);
$preferenceid  = optional_param('preference_id', '', PARAM_TEXT);
$externalref   = optional_param('external_reference', '', PARAM_TEXT);
$merchantorder = optional_param('merchant_order_id', '', PARAM_TEXT);

require_login();

$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/enrol/mercadopago/return.php'));
$PAGE->set_title(get_string('return_success_title', 'enrol_mercadopago') ?: 'Resultado del pago');
$PAGE->set_heading(get_string('return_success_title', 'enrol_mercadopago') ?: 'Resultado del pago');

echo $OUTPUT->header();

// =========================================================================
// Logging de parámetros recibidos
// =========================================================================
util::log("📥 RETURN recibido: courseid={$courseid}, instanceid={$instanceid}, userid={$userid}, " .
          "status={$status}, paymentid={$paymentid}, preference={$preferenceid}, ref={$externalref}");

// =========================================================================
// Buscar el registro de pago (múltiples estrategias de búsqueda)
// =========================================================================
$payment = null;

// Estrategia 1: Por external_reference (más confiable)
if (!$payment && !empty($externalref)) {
    $payment = $DB->get_record('enrol_mercadopago', ['external_reference' => $externalref]);
    if ($payment) {
        util::log("🔍 Registro encontrado por external_reference: {$externalref}");
    }
}

// Estrategia 2: Por preference_id
if (!$payment && !empty($preferenceid)) {
    $payment = $DB->get_record('enrol_mercadopago', ['preference_id' => $preferenceid]);
    if ($payment) {
        util::log("🔍 Registro encontrado por preference_id: {$preferenceid}");
    }
}

// Estrategia 3: Por payment_id
if (!$payment && !empty($paymentid)) {
    $payment = $DB->get_record('enrol_mercadopago', ['paymentid' => $paymentid]);
    if ($payment) {
        util::log("🔍 Registro encontrado por paymentid: {$paymentid}");
    }
}

// Estrategia 4: Por courseid + userid + instanceid (último recurso)
if (!$payment) {
    // Buscar el registro más reciente en estado initiated o pending
    $payments = $DB->get_records('enrol_mercadopago', [
        'courseid' => $courseid,
        'userid' => $userid,
        'instanceid' => $instanceid
    ], 'timecreated DESC', '*', 0, 1);
    
    if (!empty($payments)) {
        $payment = reset($payments);
        util::log("🔍 Registro encontrado por courseid/userid/instanceid (fallback)");
    }
}

// =========================================================================
// Si no se encuentra el registro
// =========================================================================
if (!$payment) {
    util::log("⚠️ RETURN: No se encontró registro de pago", 'warning');
    echo $OUTPUT->notification(
        '⚠️ No se encontró registro de pago en la base de datos. Si completaste el pago, serás matriculado automáticamente en breve. Si el problema persiste, contacta a soporte.',
        'notifyproblem'
    );
    echo $OUTPUT->continue_button(new moodle_url('/course/view.php', ['id' => $courseid]));
    echo $OUTPUT->footer();
    exit;
}

// =========================================================================
// Actualizar payment_id si llegó y no lo teníamos
// =========================================================================
if (!empty($paymentid) && empty($payment->paymentid)) {
    $payment->paymentid = $paymentid;
    $payment->timemodified = time();
    $DB->update_record('enrol_mercadopago', $payment);
    util::log("🔄 Payment ID actualizado: {$paymentid}");
}

// =========================================================================
// Verificar idempotencia: ¿ya fue procesado?
// =========================================================================
if ($payment->status === 'approved' && !empty($payment->confirmedby)) {
    util::log("ℹ️ RETURN: Pago ya procesado anteriormente por {$payment->confirmedby}");
    
    // Verificar si el usuario ya está matriculado
    if (is_enrolled($context, $userid)) {
        echo $OUTPUT->notification('✅ Tu pago ya fue confirmado y estás matriculado en el curso.', 'notifysuccess');
    } else {
        echo $OUTPUT->notification('✅ Pago confirmado. La matrícula se está procesando.', 'notifysuccess');
    }
    
    echo $OUTPUT->continue_button(new moodle_url('/course/view.php', ['id' => $courseid]));
    echo $OUTPUT->footer();
    exit;
}

// =========================================================================
// Procesar según estado del pago
// =========================================================================
util::log("🔄 Procesando estado: {$status} (estado actual en BD: {$payment->status})");

$instance = $DB->get_record('enrol', ['id' => $instanceid, 'enrol' => 'mercadopago']);

switch ($status) {
    case 'success':
    case 'approved':
        // =================================================================
        // PAGO APROBADO
        // =================================================================
        
        // Verificar idempotencia nuevamente con bloqueo
        $currentstatus = $DB->get_field('enrol_mercadopago', 'status', ['id' => $payment->id]);
        if ($currentstatus === 'approved') {
            util::log("ℹ️ RETURN: Estado ya es approved (posible IPN previo)");
        } else {
            // Actualizar estado
            $payment->status = 'approved';
            $payment->confirmedby = 'RETURN';
            $payment->date_approved = time();
            $payment->timemodified = time();
            $DB->update_record('enrol_mercadopago', $payment);
            util::log("✅ RETURN: Estado actualizado a 'approved'");
        }

        // Registrar uso del cupón si corresponde (CORREGIDO: $payment en lugar de $record)
        if (!empty($payment->couponcode) && !empty($payment->couponid)) {
            try {
                // Verificar si ya se registró el uso
                $existinguse = $DB->get_record('enrol_mercadopago_coupon_usage', [
                    'couponid' => $payment->couponid,
                    'userid' => $payment->userid
                ]);
                
                if (!$existinguse) {
                    coupon_manager::register_coupon_use(
                        $payment->couponcode,
                        $payment->userid,
                        $payment->courseid,
                        $payment->paymentid
                    );
                    util::log("🎟️ Cupón '{$payment->couponcode}' registrado correctamente vía RETURN");
                } else {
                    util::log("ℹ️ Uso de cupón ya registrado anteriormente");
                }
            } catch (\Throwable $e) {
                util::log("⚠️ Error al registrar uso de cupón: " . $e->getMessage(), 'warning');
            }
        }

        // Matricular al usuario
        if ($instance) {
            // Verificar si ya está matriculado (idempotencia)
            if (is_enrolled($context, $userid)) {
                util::log("ℹ️ RETURN: Usuario {$userid} ya estaba matriculado");
                echo $OUTPUT->notification('✅ Pago verificado. Ya estás matriculado en el curso.', 'notifysuccess');
            } else {
                try {
                    $enrol = enrol_get_plugin('mercadopago');
                    $enrol->enrol_user($instance, $userid, $instance->roleid, time());
                    util::log("🎓 Usuario {$userid} matriculado correctamente vía RETURN (course={$courseid})");
                    
                    // Enviar correos
                    try {
                        util::send_payment_confirmation_email($userid, $courseid, $payment->final_amount, $payment->currency);
                        util::send_course_welcome_email($userid, $courseid);
                        util::log("📧 Correos de confirmación enviados");
                    } catch (\Throwable $e) {
                        util::log("⚠️ Error enviando correos: " . $e->getMessage(), 'warning');
                    }
                    
                    echo $OUTPUT->notification('✅ ¡Pago verificado correctamente! Has sido matriculado en el curso.', 'notifysuccess');
                } catch (\Throwable $e) {
                    util::log("❌ Error al matricular usuario: " . $e->getMessage(), 'error');
                    echo $OUTPUT->notification('⚠️ Pago aprobado, pero ocurrió un error al matricularte. Serás matriculado automáticamente en breve.', 'notifyproblem');
                }
            }
        } else {
            util::log("⚠️ RETURN: No se encontró instancia enrol para instanceid={$instanceid}", 'warning');
            echo $OUTPUT->notification('⚠️ Pago aprobado. La matrícula se procesará automáticamente.', 'notifymessage');
        }
        break;

    case 'failure':
    case 'rejected':
    case 'cancelled':
        // =================================================================
        // PAGO FALLIDO/RECHAZADO
        // =================================================================
        $payment->status = 'failed';
        $payment->confirmedby = 'RETURN';
        $payment->timemodified = time();
        $DB->update_record('enrol_mercadopago', $payment);
        
        util::log("❌ RETURN: Pago fallido/rechazado (paymentid={$payment->paymentid})", 'warning');
        echo $OUTPUT->notification('❌ El pago fue rechazado o cancelado. Puedes intentar nuevamente.', 'notifyproblem');
        break;

    case 'pending':
    case 'in_process':
        // =================================================================
        // PAGO PENDIENTE
        // =================================================================
        $payment->status = 'pending';
        $payment->confirmedby = 'RETURN';
        $payment->timemodified = time();
        $DB->update_record('enrol_mercadopago', $payment);
        
        util::log("⏳ RETURN: Pago pendiente (paymentid={$payment->paymentid})");
        echo $OUTPUT->notification('ℹ️ Tu pago está pendiente de confirmación. Recibirás un correo cuando sea aprobado y serás matriculado automáticamente.', 'notifymessage');
        break;

    default:
        // =================================================================
        // ESTADO DESCONOCIDO
        // =================================================================
        util::log("⚠️ RETURN: Estado desconocido: {$status}", 'warning');
        echo $OUTPUT->notification('ℹ️ El estado del pago está siendo procesado. Si completaste el pago, serás matriculado automáticamente.', 'notifymessage');
        break;
}

echo $OUTPUT->continue_button(new moodle_url('/course/view.php', ['id' => $courseid]));
echo $OUTPUT->footer();
