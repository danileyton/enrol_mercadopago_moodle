<?php
// ============================================================================
// enrol/mercadopago/reprocess.php
// Permite reprocesar manualmente una matrícula confirmada de Mercado Pago.
// Solo para administradores.
// ============================================================================

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/enrol/mercadopago/classes/util.php');

use enrol_mercadopago\util;

// --- Seguridad y contexto ---
require_login();
require_sesskey();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

// --- Parámetro: ID del registro en la tabla enrol_mercadopago ---
$id = required_param('id', PARAM_INT);
global $DB, $OUTPUT;

// --- Obtener el registro del pago ---
$record = $DB->get_record('enrol_mercadopago', ['id' => $id], '*', MUST_EXIST);

if (empty($record->userid) || empty($record->courseid) || empty($record->instanceid)) {
    print_error('Datos insuficientes para reprocesar la matrícula.');
}

$courseid   = $record->courseid;
$userid     = $record->userid;
$instanceid = $record->instanceid;

// --- Reprocesar ---
$plugin = enrol_get_plugin('mercadopago');
if (!$plugin) {
    print_error('Plugin enrol_mercadopago no disponible.');
}

$instance = $DB->get_record('enrol', ['id' => $instanceid, 'enrol' => 'mercadopago']);
if (!$instance) {
    print_error('Instancia de matrícula no encontrada.');
}

// Evitar duplicado
$context = context_course::instance($courseid);
if (is_enrolled($context, $userid)) {
    redirect(
        new moodle_url('/enrol/mercadopago/report.php'),
        'ℹ️ El usuario ya estaba matriculado en el curso.',
        null,
        \core\output\notification::NOTIFY_INFO
    );
    exit;
}

// --- Matricular ---
$plugin->enrol_user($instance, $userid, $instance->roleid, time());
util::log("🔁 Reproceso manual: usuario {$userid} matriculado en curso {$courseid}.");

// --- Confirmación visual ---
redirect(
    new moodle_url('/enrol/mercadopago/report.php'),
    '✅ Matrícula reprocesada correctamente.',
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
