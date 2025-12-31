<?php
namespace enrol_mercadopago\task;

defined('MOODLE_INTERNAL') || die();

use enrol_mercadopago\util;

/**
 * Tarea programada: sincroniza matrículas pendientes por pagos aprobados.
 */
class sync_enrolments_task extends \core\task\scheduled_task {

    /**
     * Nombre que se mostrará en la interfaz de administración de tareas.
     */
    public function get_name() {
        return get_string('tasksyncenrolments', 'enrol_mercadopago');
    }

    /**
     * Lógica principal de sincronización.
     */
    public function execute() {
        global $DB;

        mtrace("[MercadoPago Plugin] Iniciando sincronización de matrículas...");

        // Buscar pagos aprobados sin matrícula activa.
        $records = $DB->get_records_sql("
            SELECT mp.*, e.courseid, e.roleid
            FROM {enrol_mercadopago} mp
            JOIN {enrol} e ON e.id = mp.instanceid
            WHERE mp.status = 'approved'
            AND NOT EXISTS (
                SELECT 1 FROM {user_enrolments} ue
                WHERE ue.userid = mp.userid AND ue.enrolid = e.id
            )
        ");

        if (empty($records)) {
            mtrace(" - No hay matrículas pendientes por procesar.");
            return;
        }

        foreach ($records as $r) {
            try {
                mtrace(" → Reprocesando pago {$r->paymentid} (User: {$r->userid}, Course: {$r->courseid})");
                util::process_successful_payment((object)[
                    'id' => $r->paymentid,
                    'transaction_amount' => $r->amount,
                    'payer' => (object)['email' => ''],
                    'metadata' => (object)[
                        'userid' => $r->userid,
                        'courseid' => $r->courseid,
                        'instanceid' => $r->instanceid
                    ]
                ]);
            } catch (\Throwable $e) {
                mtrace(" ❌ Error en pago {$r->paymentid}: " . $e->getMessage());
            }
        }

        mtrace("[MercadoPago Plugin] Sincronización completada.");
    }
}
