<?php
namespace enrol_mercadopago\task;

use core\task\scheduled_task;
use enrol_mercadopago\util;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;

defined('MOODLE_INTERNAL') || die();

/**
 * Tarea cron para verificar pagos pendientes en Mercado Pago.
 */
class check_pending_payments_task extends scheduled_task {

    public function get_name() {
        return get_string('check_pending_payments_task', 'enrol_mercadopago');
    }

    public function execute() {
        global $DB;

        util::log('🔍 [CRON] Iniciando verificación de pagos pendientes en Mercado Pago.');

        $pendingpayments = $DB->get_records('enrol_mercadopago', ['status' => 'pending']);
        if (empty($pendingpayments)) {
            util::log('ℹ️ [CRON] No se encontraron pagos pendientes.');
            return true;
        }

        $token = get_config('enrol_mercadopago', 'accesstoken');
        if (empty($token)) {
            util::log('❌ [CRON] Access token de Mercado Pago no configurado.');
            return false;
        }

        MercadoPagoConfig::setAccessToken($token);
        $client = new PaymentClient();

        foreach ($pendingpayments as $payment) {
            try {
                $mp = $client->get($payment->paymentid);
                $status = strtolower($mp->status ?? '');

                util::log("📦 [CRON] Verificando pago {$payment->paymentid} (estado actual en MP: {$status}).");

                if ($status === 'approved') {
                    util::log("✅ [CRON] Pago {$payment->paymentid} aprobado, procesando matrícula...");

                    $DB->set_field('enrol_mercadopago', 'status', 'approved', ['id' => $payment->id]);
                    $DB->set_field('enrol_mercadopago', 'date_approved', time(), ['id' => $payment->id]);

                    util::process_successful_payment((object)[
                        'id' => $payment->paymentid,
                        'metadata' => (object)[
                            'userid' => $payment->userid,
                            'courseid' => $payment->courseid,
                            'instanceid' => $payment->instanceid
                        ],
                        'transaction_amount' => $payment->amount
                    ]);

                    util::log("🎓 [CRON] Usuario {$payment->userid} matriculado correctamente en curso {$payment->courseid} (pago {$payment->paymentid}).");
                }
            } catch (\Exception $e) {
                util::log("❌ [CRON] Error al verificar pago {$payment->paymentid}: " . $e->getMessage());
            }
        }

        util::log('🏁 [CRON] Verificación de pagos pendientes finalizada.');
        return true;
    }
}
