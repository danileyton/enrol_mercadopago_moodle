<?php
namespace enrol_mercadopago;

defined('MOODLE_INTERNAL') || die();

use core_user;
use moodle_url;

/**
 * Clase utilitaria para procesar pagos y matricular usuarios
 * Plugin: enrol_mercadopago (SDK v3)
 */
class util {

    /**
     * Procesa un pago aprobado desde IPN o return.php
     * - Reconstruye metadatos desde BD si faltan.
     * - Verifica usuario, curso e instancia antes de matricular.
     * - Es idempotente (evita duplicar matrículas o correos).
     *
     * @param object $paymentdata
     */
    public static function process_successful_payment($paymentdata) {
        global $DB;

        $paymentid  = $paymentdata->id ?? 0;
        $amount     = $paymentdata->transaction_amount ?? 0;
        $metadata   = $paymentdata->metadata ?? (object)[];

        $userid     = $metadata->userid     ?? 0;
        $courseid   = $metadata->courseid   ?? 0;
        $instanceid = $metadata->instanceid ?? 0;

        // ---------------------------------------------------------------------
        // 1️⃣ Reforzar datos desde la BD si faltan
        // ---------------------------------------------------------------------
        if ((!$userid || !$courseid || !$instanceid) && !empty($paymentid)) {
            $rec = $DB->get_record('enrol_mercadopago', ['paymentid' => $paymentid]);
            if ($rec) {
                $userid     = $userid ?: (int)$rec->userid;
                $courseid   = $courseid ?: (int)$rec->courseid;
                $instanceid = $instanceid ?: (int)$rec->instanceid;
            }
        }

        // ---------------------------------------------------------------------
        // 2️⃣ Validaciones mínimas
        // ---------------------------------------------------------------------
        if (empty($userid) || empty($courseid) || empty($instanceid)) {
            self::log("❌ Pago {$paymentid}: metadatos incompletos. userid={$userid}, courseid={$courseid}, instanceid={$instanceid}");
            return;
        }

        if (!$user = $DB->get_record('user', ['id' => $userid])) {
            self::log("❌ Usuario {$userid} no encontrado. No se puede matricular.");
            return;
        }

        if (!$course = $DB->get_record('course', ['id' => $courseid])) {
            self::log("❌ Curso {$courseid} no encontrado. No se puede matricular.");
            return;
        }

        if (!$instance = $DB->get_record('enrol', ['id' => $instanceid, 'enrol' => 'mercadopago'])) {
            self::log("❌ Instancia de matrícula {$instanceid} no encontrada (mercadopago).");
            return;
        }

        // ---------------------------------------------------------------------
        // 3️⃣ Verificar si ya está matriculado (idempotencia)
        // ---------------------------------------------------------------------
        $context = \context_course::instance($courseid);
        if (is_enrolled($context, $userid)) {
            self::log("ℹ️ Pago {$paymentid}: Usuario {$userid} ya estaba matriculado en el curso {$courseid}. Se omite matrícula duplicada.");
            return;
        }

        // ---------------------------------------------------------------------
        // 4️⃣ Matricular al usuario
        // ---------------------------------------------------------------------
        $plugin = enrol_get_plugin('mercadopago');
        if (!$plugin) {
            self::log('❌ Plugin enrol_mercadopago no disponible.');
            return;
        }

        $plugin->enrol_user($instance, $userid, $instance->roleid, time());
        self::log("✅ Usuario {$userid} matriculado correctamente en el curso {$courseid} por pago {$paymentid}.");

        // ---------------------------------------------------------------------
        // 5️⃣ Actualizar registro en BD (marcar pagado)
        // ---------------------------------------------------------------------
        if ($record = $DB->get_record('enrol_mercadopago', ['paymentid' => $paymentid])) {
            $record->status = 'approved';
            $record->timeupdated = time();
            $DB->update_record('enrol_mercadopago', $record);
        }

        // ---------------------------------------------------------------------
        // 6️⃣ Enviar correos de confirmación y bienvenida (plantillas HTML)
        // ---------------------------------------------------------------------
        try {
            self::send_payment_confirmation_email($userid, $courseid, $amount, 'CLP');
            self::send_course_welcome_email($userid, $courseid);
            self::log("📧 Correos enviados correctamente al usuario {$userid} para el curso {$courseid} (pago {$paymentid}).");
        } catch (\Throwable $e) {
            self::log("⚠️ Error al enviar correos al usuario {$userid}: " . $e->getMessage(), 'warning');
        }
    }

    // -------------------------------------------------------------------------
    // 📩 Envío de correos con plantillas HTML
    // -------------------------------------------------------------------------

    public static function send_payment_confirmation_email($userid, $courseid, $amount, $currency) {
        global $CFG, $DB;

        $user = $DB->get_record('user', ['id' => $userid]);
        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$user || !$course) {
            return false;
        }

        $templatepath = $CFG->dirroot . '/enrol/mercadopago/templates/email_payment_confirmation.html';
        if (!file_exists($templatepath)) {
            self::log('⚠️ Plantilla de confirmación de pago no encontrada.');
            return false;
        }

        $body = file_get_contents($templatepath);
        $courseurl = (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false);

        $body = str_replace(
            ['{{firstname}}', '{{coursename}}', '{{courseurl}}', '{{amount}}', '{{currency}}'],
            [$user->firstname, $course->fullname, $courseurl, $amount, $currency],
            $body
        );

        $subject = "[Academia CONAC] Confirmación de pago - {$course->fullname}";
        email_to_user($user, core_user::get_support_user(), $subject, strip_tags($body), $body);
        return true;
    }

    public static function send_course_welcome_email($userid, $courseid) {
        global $CFG, $DB;

        $user = $DB->get_record('user', ['id' => $userid]);
        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$user || !$course) {
            return false;
        }

        $templatepath = $CFG->dirroot . '/enrol/mercadopago/templates/email_welcome.html';
        if (!file_exists($templatepath)) {
            self::log('⚠️ Plantilla de bienvenida no encontrada.');
            return false;
        }

        $body = file_get_contents($templatepath);
        $courseurl = (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false);

        $body = str_replace(
            ['{{firstname}}', '{{coursename}}', '{{courseurl}}'],
            [$user->firstname, $course->fullname, $courseurl],
            $body
        );

        $subject = "[Academia CONAC] Bienvenida - {$course->fullname}";
        email_to_user($user, core_user::get_support_user(), $subject, strip_tags($body), $body);
        return true;
    }

    // -------------------------------------------------------------------------
    // 🧰 Logger
    // -------------------------------------------------------------------------
    public static function log($message, $level = 'info') {
        global $DB;
        $record = (object)[
            'timecreated' => time(),
            'level' => $level,
            'message' => $message
        ];
        try {
            $DB->insert_record('enrol_mercadopago_log', $record);
        } catch (\Throwable $e) {
            debugging("No se pudo registrar log en enrol_mercadopago_log: " . $e->getMessage());
        }
    }
}
