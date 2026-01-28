<?php
namespace enrol_mercadopago;

defined('MOODLE_INTERNAL') || die();

/**
 * Gestor de cupones de descuento para el plugin enrol_mercadopago.
 * 
 * Funcionalidades:
 * - Validación de cupones (código, fechas, límites, elegibilidad)
 * - Cálculo de descuentos
 * - Registro de uso de cupones
 * - Verificación de elegibilidad por cohortes
 * 
 * @package    enrol_mercadopago
 */
class coupon_manager {

    /**
     * Valida un cupón para un curso específico.
     * Verifica: existencia, estado activo, fechas, límite de usos globales.
     * NO verifica elegibilidad por cohorte ni uso previo por usuario.
     * 
     * @param int $courseid ID del curso
     * @param string $code Código del cupón
     * @return object|false El cupón si es válido, false si no
     */
    public static function validate_coupon($courseid, $code) {
        global $DB;

        $code = strtoupper(trim($code));
        
        if (empty($code)) {
            return false;
        }

        $coupon = $DB->get_record('enrol_mercadopago_coupons', [
            'courseid' => $courseid,
            'code' => $code
        ]);

        if (!$coupon) {
            // Intentar buscar cupón global (courseid = 0)
            $coupon = $DB->get_record('enrol_mercadopago_coupons', [
                'courseid' => 0,
                'code' => $code
            ]);
        }

        if (!$coupon) {
            return false;
        }

        // Verificar si está activo
        if (!$coupon->active) {
            return false;
        }

        $now = time();

        // Validar fecha de inicio
        if (!empty($coupon->validfrom) && $coupon->validfrom > 0 && $now < $coupon->validfrom) {
            return false;
        }

        // Validar fecha de fin
        if (!empty($coupon->validuntil) && $coupon->validuntil > 0 && $now > $coupon->validuntil) {
            return false;
        }

        // Validar límite de usos globales
        if ($coupon->maxuses > 0 && $coupon->usedcount >= $coupon->maxuses) {
            return false;
        }

        return $coupon;
    }

    /**
     * Valida un cupón con verificaciones completas incluyendo elegibilidad y uso previo.
     * 
     * @param int $courseid ID del curso
     * @param string $code Código del cupón
     * @param int $userid ID del usuario
     * @return array ['valid' => bool, 'coupon' => object|null, 'message' => string]
     */
    public static function validate_coupon_full($courseid, $code, $userid) {
        global $DB;

        $code = strtoupper(trim($code));
        
        if (empty($code)) {
            return [
                'valid' => false,
                'coupon' => null,
                'message' => get_string('coupon_empty', 'enrol_mercadopago') ?: 'Por favor ingresa un código de cupón.'
            ];
        }

        // Validación básica
        $coupon = self::validate_coupon($courseid, $code);
        
        if (!$coupon) {
            // Verificar si existe pero está inactivo o expirado para dar mensaje específico
            $existingcoupon = $DB->get_record('enrol_mercadopago_coupons', [
                'courseid' => $courseid,
                'code' => $code
            ]);
            
            if (!$existingcoupon) {
                $existingcoupon = $DB->get_record('enrol_mercadopago_coupons', [
                    'courseid' => 0,
                    'code' => $code
                ]);
            }
            
            if ($existingcoupon) {
                $now = time();
                
                if (!$existingcoupon->active) {
                    return ['valid' => false, 'coupon' => null, 
                            'message' => get_string('coupon_inactive', 'enrol_mercadopago') ?: '❌ Este cupón ya no está disponible.'];
                }
                
                if (!empty($existingcoupon->validfrom) && $now < $existingcoupon->validfrom) {
                    return ['valid' => false, 'coupon' => null,
                            'message' => '⏳ Este cupón será válido a partir del ' . date('d/m/Y', $existingcoupon->validfrom)];
                }
                
                if (!empty($existingcoupon->validuntil) && $now > $existingcoupon->validuntil) {
                    return ['valid' => false, 'coupon' => null,
                            'message' => '⏰ Este cupón expiró el ' . date('d/m/Y', $existingcoupon->validuntil)];
                }
                
                if ($existingcoupon->maxuses > 0 && $existingcoupon->usedcount >= $existingcoupon->maxuses) {
                    return ['valid' => false, 'coupon' => null,
                            'message' => get_string('coupon_exhausted', 'enrol_mercadopago') ?: '🎫 Este cupón ha alcanzado su límite de usos.'];
                }
            }
            
            return ['valid' => false, 'coupon' => null,
                    'message' => get_string('coupon_invalid', 'enrol_mercadopago') ?: '❌ Cupón no válido. Verifica el código e intenta nuevamente.'];
        }

        // Verificar uso previo por el usuario
        $previoususe = $DB->get_record('enrol_mercadopago_coupon_usage', [
            'couponid' => $coupon->id,
            'userid' => $userid
        ]);
        
        if ($previoususe) {
            return ['valid' => false, 'coupon' => null,
                    'message' => get_string('coupon_already_used', 'enrol_mercadopago') ?: '⚠️ Ya has utilizado este cupón anteriormente.'];
        }

        // Verificar elegibilidad por cohorte si es cupón restringido
        if ($coupon->eligibility_type === 'restricted') {
            if (!self::check_cohort_eligibility($coupon->id, $userid)) {
                return ['valid' => false, 'coupon' => null,
                        'message' => get_string('coupon_not_eligible', 'enrol_mercadopago') ?: '🚫 No tienes acceso a este cupón.'];
            }
        }

        return ['valid' => true, 'coupon' => $coupon, 'message' => ''];
    }

    /**
     * Verifica si un usuario es elegible para usar un cupón restringido.
     * Comprueba membresía en las cohortes asociadas al cupón.
     * 
     * @param int $couponid ID del cupón
     * @param int $userid ID del usuario
     * @return bool True si es elegible
     */
    public static function check_cohort_eligibility($couponid, $userid) {
        global $DB;

        // Obtener las cohortes asociadas al cupón
        $cohorts = $DB->get_records('enrol_mercadopago_coupon_cohorts', ['couponid' => $couponid]);

        if (empty($cohorts)) {
            // Si no hay cohortes definidas, nadie puede usar el cupón restringido
            return false;
        }

        // Verificar si el usuario pertenece a alguna de las cohortes
        require_once(__DIR__ . '/../../../cohort/lib.php');
        
        foreach ($cohorts as $cc) {
            if (cohort_is_member($cc->cohortid, $userid)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calcula el monto final aplicando el descuento del cupón.
     * 
     * @param float $originalamount Monto original
     * @param object $coupon Objeto del cupón
     * @return float Monto final después del descuento
     */
    public static function apply_discount($originalamount, $coupon) {
        if ($coupon->type === 'percent') {
            $discount = round($originalamount * ($coupon->value / 100), 2);
        } else {
            $discount = (float)$coupon->value;
        }
        
        return max(0, $originalamount - $discount);
    }

    /**
     * Calcula el monto del descuento.
     * 
     * @param float $originalamount Monto original
     * @param object $coupon Objeto del cupón
     * @return float Monto del descuento
     */
    public static function calculate_discount($originalamount, $coupon) {
        if ($coupon->type === 'percent') {
            return round($originalamount * ($coupon->value / 100), 2);
        } else {
            return min((float)$coupon->value, $originalamount);
        }
    }

    /**
     * Registra el uso de un cupón después de un pago aprobado.
     * 
     * @param string $couponcode Código del cupón
     * @param int $userid ID del usuario
     * @param int $courseid ID del curso
     * @param string|null $paymentid ID del pago en MP
     * @return bool True si se registró correctamente
     */
    public static function register_coupon_use($couponcode, $userid, $courseid, $paymentid = null) {
        global $DB;

        $couponcode = strtoupper(trim($couponcode));

        // Buscar el cupón
        $coupon = $DB->get_record('enrol_mercadopago_coupons', [
            'courseid' => $courseid,
            'code' => $couponcode
        ]);

        if (!$coupon) {
            // Intentar con cupón global
            $coupon = $DB->get_record('enrol_mercadopago_coupons', [
                'courseid' => 0,
                'code' => $couponcode
            ]);
        }

        if (!$coupon) {
            util::log("⚠️ register_coupon_use: Cupón no encontrado: {$couponcode}", 'warning');
            return false;
        }

        // Verificar si ya existe un registro de uso
        $existinguse = $DB->get_record('enrol_mercadopago_coupon_usage', [
            'couponid' => $coupon->id,
            'userid' => $userid
        ]);

        if ($existinguse) {
            util::log("ℹ️ Uso de cupón ya registrado (coupon={$couponcode}, user={$userid})");
            return true; // Ya está registrado, no es un error
        }

        // Usar transacción para evitar race conditions
        $transaction = $DB->start_delegated_transaction();

        try {
            // Incrementar contador de uso del cupón
            $coupon->usedcount = (int)$coupon->usedcount + 1;

            // Desactivar si alcanzó el máximo
            if ($coupon->maxuses > 0 && $coupon->usedcount >= $coupon->maxuses) {
                $coupon->active = 0;
            }

            $coupon->timemodified = time();
            $DB->update_record('enrol_mercadopago_coupons', $coupon);

            // Registrar el uso en la tabla de auditoría
            $usage = new \stdClass();
            $usage->couponid = $coupon->id;
            $usage->userid = $userid;
            $usage->courseid = $courseid;
            $usage->paymentid = $paymentid;
            $usage->timecreated = time();
            $DB->insert_record('enrol_mercadopago_coupon_usage', $usage);

            $transaction->allow_commit();

            // Log de auditoría
            $maxuses = $coupon->maxuses > 0 ? $coupon->maxuses : '∞';
            $status = $coupon->active ? 'Activo' : 'Agotado';
            util::log("🎟️ Cupón usado: {$coupon->code} | Curso: {$courseid} | Usuario: {$userid} | " .
                     "Usos: {$coupon->usedcount}/{$maxuses} | Estado: {$status}");

            return true;

        } catch (\Exception $e) {
            $transaction->rollback($e);
            util::log("❌ Error registrando uso de cupón: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Obtiene las cohortes asociadas a un cupón.
     * 
     * @param int $couponid ID del cupón
     * @return array Lista de objetos cohorte
     */
    public static function get_coupon_cohorts($couponid) {
        global $DB;

        $sql = "SELECT c.* 
                FROM {cohort} c
                JOIN {enrol_mercadopago_coupon_cohorts} cc ON cc.cohortid = c.id
                WHERE cc.couponid = ?
                ORDER BY c.name ASC";

        return $DB->get_records_sql($sql, [$couponid]);
    }

    /**
     * Asocia cohortes a un cupón.
     * 
     * @param int $couponid ID del cupón
     * @param array $cohortids Array de IDs de cohortes
     * @return bool
     */
    public static function set_coupon_cohorts($couponid, $cohortids) {
        global $DB;

        // Eliminar asociaciones existentes
        $DB->delete_records('enrol_mercadopago_coupon_cohorts', ['couponid' => $couponid]);

        // Crear nuevas asociaciones
        foreach ($cohortids as $cohortid) {
            $record = new \stdClass();
            $record->couponid = $couponid;
            $record->cohortid = $cohortid;
            $DB->insert_record('enrol_mercadopago_coupon_cohorts', $record);
        }

        return true;
    }

    /**
     * Obtiene el historial de uso de un cupón.
     * 
     * @param int $couponid ID del cupón
     * @return array Lista de registros de uso
     */
    public static function get_coupon_usage_history($couponid) {
        global $DB;

        $sql = "SELECT cu.*, u.firstname, u.lastname, u.email, c.fullname as coursename
                FROM {enrol_mercadopago_coupon_usage} cu
                JOIN {user} u ON u.id = cu.userid
                JOIN {course} c ON c.id = cu.courseid
                WHERE cu.couponid = ?
                ORDER BY cu.timecreated DESC";

        return $DB->get_records_sql($sql, [$couponid]);
    }
}
