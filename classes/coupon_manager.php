<?php
namespace enrol_mercadopago;

defined('MOODLE_INTERNAL') || die();

class coupon_manager {

    /**
     * Valida un cupón para un curso específico.
     * Retorna false si está inactivo, vencido o agotado.
     */
    public static function validate_coupon($courseid, $code) {
        global $DB;

        $coupon = $DB->get_record('enrol_mercadopago_coupons', [
            'courseid' => $courseid,
            'code' => strtoupper(trim($code))
        ]);

        if (!$coupon) {
            return false;
        }

        $now = time();

        // Verificar si está activo.
        if (!$coupon->active) {
            return false;
        }

        // Validar fechas.
        if ($coupon->validfrom && $now < $coupon->validfrom) {
            return false;
        }

        if ($coupon->validuntil && $now > $coupon->validuntil) {
            return false;
        }

        // Validar límite de usos.
        if ($coupon->maxuses > 0 && $coupon->usedcount >= $coupon->maxuses) {
            return false;
        }

        return $coupon;
    }

    /**
     * Calcula el monto final aplicando el cupón.
     */
    public static function apply_discount($originalamount, $coupon) {
        if ($coupon->type === 'percent') {
            $discount = ($originalamount * ($coupon->value / 100));
        } else {
            $discount = $coupon->value;
        }
        return max(0, $originalamount - $discount);
    }

    /**
     * Registra el uso de un cupón después de un pago aprobado.
     */
    public static function register_coupon_use($couponcode, $userid, $courseid, $paymentid = null) {
        global $DB;

        $coupon = $DB->get_record('enrol_mercadopago_coupons', [
            'courseid' => $courseid,
            'code' => strtoupper(trim($couponcode))
        ]);

        if (!$coupon) {
            return false;
        }

        // Incrementar contador de uso.
        $coupon->usedcount = (int)$coupon->usedcount + 1;

        // Desactivar si alcanzó el máximo.
        if ($coupon->maxuses > 0 && $coupon->usedcount >= $coupon->maxuses) {
            $coupon->active = 0;
        }

        $coupon->timemodified = time();
        $DB->update_record('enrol_mercadopago_coupons', $coupon);

        // Guardar registro de auditoría (opcional)
        self::log_coupon_use($coupon, $userid, $paymentid);

        return true;
    }

    /**
     * (Opcional) Registra el uso del cupón en archivo de log de Moodle.
     */
    private static function log_coupon_use($coupon, $userid, $paymentid = null) {
        $msg = "🧾 Cupón usado: {$coupon->code} | Curso: {$coupon->courseid} | Usuario: {$userid} | Usos: {$coupon->usedcount}/{$coupon->maxuses} | Estado: " . ($coupon->active ? 'Activo' : 'Inactivo');
        \enrol_mercadopago\util::log($msg);
    }
}
