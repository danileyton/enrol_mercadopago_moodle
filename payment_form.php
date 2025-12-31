<?php
// ============================================================================
// payment_form.php (ENROL MERCADOPAGO)
// Archivo de entrada para renderizar el bot¨®n de pago.
// Redirige la ejecuci¨®n hacia la clase principal del plugin en classes/form/payment_form.php
// ============================================================================

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/enrol/mercadopago/classes/form/payment_form.php');

use enrol_mercadopago\form\payment_form;

/**
 * Renderiza el bot¨®n de pago de Mercado Pago
 * para el curso, usuario e instancia de enrolment actual.
 */
function enrol_mercadopago_render_payment($course, $user, $instance) {
    return payment_form::render_payment_button($course, $user, $instance);
}
