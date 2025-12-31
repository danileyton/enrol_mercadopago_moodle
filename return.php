<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->dirroot . '/enrol/mercadopago/classes/util.php');

use enrol_mercadopago\util;

global $DB, $USER;

$courseid   = required_param('courseid', PARAM_INT);
$instanceid = required_param('instanceid', PARAM_INT);
$userid     = required_param('userid', PARAM_INT);
$status     = optional_param('status', '', PARAM_TEXT);
$paymentid  = optional_param('payment_id', '', PARAM_TEXT);
$preference = optional_param('preference_id', '', PARAM_TEXT);

require_login();
$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/enrol/mercadopago/return.php'));
$PAGE->set_title('Resultado del pago');
$PAGE->set_heading('Resultado del pago');

echo $OUTPUT->header();

// 🔍 Registro de parámetros recibidos
util::log("📥 RETURN recibido: courseid={$courseid}, instanceid={$instanceid}, userid={$userid}, status={$status}, paymentid={$paymentid}, preference={$preference}");

// Buscar el registro de pago
$params = ['courseid' => $courseid, 'instanceid' => $instanceid, 'userid' => $userid];
$payment = $DB->get_record('enrol_mercadopago', $params);

if (!$payment && !empty($paymentid)) {
    $payment = $DB->get_record('enrol_mercadopago', ['paymentid' => $paymentid]);
}
if (!$payment && !empty($preference)) {
    $payment = $DB->get_record('enrol_mercadopago', ['paymentid' => $preference]);
}

// Si no se encuentra el pago
if (!$payment) {
    util::log("⚠️ RETURN sin registro de pago coincidente", 'warning');
    echo $OUTPUT->notification('⚠️ No se encontró registro de pago en la base de datos. Contacta soporte.', 'notifyproblem');
    echo $OUTPUT->continue_button(new moodle_url('/course/view.php', ['id' => $courseid]));
    echo $OUTPUT->footer();
    exit;
}

// --- Actualización de estado ---
util::log("🔄 Actualizando estado del pago (actual={$payment->status})");

switch ($status) {
    case 'success':
    case 'approved':
        $payment->status = 'approved';
        $payment->timemodified = time();
        $DB->update_record('enrol_mercadopago', $payment);

        util::log("✅ Pago aprobado. ID={$payment->id}, paymentid={$payment->paymentid}");

        // Matricular al usuario
        $enrol = enrol_get_plugin('mercadopago');
        $instance = $DB->get_record('enrol', ['id' => $instanceid, 'enrol' => 'mercadopago']);
        
        // 🧩 NUEVO: Registrar uso del cupón si corresponde
        if (!empty($record->couponcode)) {
            require_once($CFG->dirroot . '/enrol/mercadopago/classes/coupon_manager.php');
            \enrol_mercadopago\coupon_manager::register_coupon_use(
                $record->couponcode,
                $record->userid,
                $record->courseid,
                $record->paymentid ?? null
            );
        }

        if ($instance) {
            try {
                $enrol->enrol_user($instance, $userid, $instance->roleid, time());
                util::log("🎓 Usuario matriculado correctamente (user={$userid}, course={$courseid}).");
                echo $OUTPUT->notification('✅ Pago verificado correctamente. Has sido matriculado en el curso.', 'notifysuccess');
            } catch (Throwable $e) {
                util::log("❌ Error al matricular usuario: " . $e->getMessage(), 'error');
                echo $OUTPUT->notification('⚠️ Pago aprobado, pero ocurrió un error al matricularte. Contacta soporte.', 'notifyproblem');
            }
        } else {
            util::log("⚠️ No se encontró instancia enrol para instanceid={$instanceid}", 'warning');
            echo $OUTPUT->notification('⚠️ Pago aprobado, pero no se pudo completar la matrícula.', 'notifyproblem');
        }
        break;

    case 'failure':
        $payment->status = 'failed';
        $payment->timemodified = time();
        $DB->update_record('enrol_mercadopago', $payment);
        util::log("❌ Pago fallido (paymentid={$payment->paymentid})", 'error');
        echo $OUTPUT->notification('❌ El pago fue rechazado o cancelado.', 'notifyproblem');
        break;

    default:
        util::log("ℹ️ Pago con estado pendiente o indefinido ({$status})", 'info');
        echo $OUTPUT->notification('ℹ️ El pago aún está pendiente de confirmación.', 'notifymessage');
        break;
}

echo $OUTPUT->continue_button(new moodle_url('/course/view.php', ['id' => $courseid]));
echo $OUTPUT->footer();
