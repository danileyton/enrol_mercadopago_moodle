<?php
// This file is part of Moodle - http://moodle.org/
//
// MercadoPago enrolment plugin - Database upgrade script
// @package    enrol_mercadopago
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Actualizaciones del plugin enrol_mercadopago.
 * Crea/ajusta tablas cuando se actualiza el plugin en una plataforma existente.
 *
 * @param int $oldversion Versión anterior del plugin
 * @return bool
 */
function xmldb_enrol_mercadopago_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // =========================================================================
    // UPGRADE 2025012800: Correcciones críticas Fase 1
    // - Agregar campos faltantes a enrol_mercadopago
    // - Agregar campo level a enrol_mercadopago_log
    // - Crear tabla enrol_mercadopago_coupons (si no existe)
    // - Crear tabla enrol_mercadopago_coupon_cohorts
    // - Crear tabla enrol_mercadopago_coupon_usage
    // =========================================================================
    if ($oldversion < 2025012800) {

        // -----------------------------------------------------------------
        // 1. TABLA enrol_mercadopago: agregar campos faltantes
        // -----------------------------------------------------------------
        $table = new xmldb_table('enrol_mercadopago');

        if ($dbman->table_exists($table)) {
            
            // Campo preference_id (para tracking antes del pago)
            $field = new xmldb_field('preference_id', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'paymentid');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            // Campo confirmedby (IPN, RETURN, CRON)
            $field = new xmldb_field('confirmedby', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'status_detail');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            // Campo discount
            $field = new xmldb_field('discount', XMLDB_TYPE_NUMBER, '12, 2', null, null, null, '0.00', 'amount');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            // Campo final_amount
            $field = new xmldb_field('final_amount', XMLDB_TYPE_NUMBER, '12, 2', null, null, null, '0.00', 'discount');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            // Campo couponcode
            $field = new xmldb_field('couponcode', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'currency');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            // Campo couponid
            $field = new xmldb_field('couponid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'couponcode');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            // Campo timemodified
            $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'timecreated');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            // Modificar campo status para tener default 'initiated'
            $field = new xmldb_field('status', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'initiated');
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_default($table, $field);
            }

            // Hacer paymentid nullable (no se conoce hasta después del pago)
            $field = new xmldb_field('paymentid', XMLDB_TYPE_CHAR, '64', null, null, null, null);
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_notnull($table, $field);
            }

            // Índice en preference_id
            $index = new xmldb_index('preference_id_idx', XMLDB_INDEX_NOTUNIQUE, ['preference_id']);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }

            // Índice en status
            $index = new xmldb_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }

            // Eliminar índice único de paymentid (puede ser null ahora)
            $index = new xmldb_index('paymentid_uix', XMLDB_INDEX_UNIQUE, ['paymentid']);
            if ($dbman->index_exists($table, $index)) {
                $dbman->drop_index($table, $index);
            }

            // Crear índice no único en paymentid
            $index = new xmldb_index('paymentid_idx', XMLDB_INDEX_NOTUNIQUE, ['paymentid']);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        // -----------------------------------------------------------------
        // 2. TABLA enrol_mercadopago_log: agregar campo level
        // -----------------------------------------------------------------
        $logtable = new xmldb_table('enrol_mercadopago_log');
        
        if ($dbman->table_exists($logtable)) {
            $field = new xmldb_field('level', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'info', 'id');
            if (!$dbman->field_exists($logtable, $field)) {
                $dbman->add_field($logtable, $field);
            }

            // Índice en level
            $index = new xmldb_index('level_idx', XMLDB_INDEX_NOTUNIQUE, ['level']);
            if (!$dbman->index_exists($logtable, $index)) {
                $dbman->add_index($logtable, $index);
            }
        }

        // -----------------------------------------------------------------
        // 3. TABLA enrol_mercadopago_coupons: crear si no existe
        // -----------------------------------------------------------------
        $coupontable = new xmldb_table('enrol_mercadopago_coupons');

        if (!$dbman->table_exists($coupontable)) {
            $coupontable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $coupontable->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $coupontable->add_field('code', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $coupontable->add_field('type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'percent');
            $coupontable->add_field('value', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0.00');
            $coupontable->add_field('eligibility_type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'open');
            $coupontable->add_field('validfrom', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $coupontable->add_field('validuntil', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $coupontable->add_field('maxuses', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $coupontable->add_field('usedcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $coupontable->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $coupontable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $coupontable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

            $coupontable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $coupontable->add_index('courseid_code_uix', XMLDB_INDEX_UNIQUE, ['courseid', 'code']);
            $coupontable->add_index('active_idx', XMLDB_INDEX_NOTUNIQUE, ['active']);

            $dbman->create_table($coupontable);
        } else {
            // Si la tabla existe, agregar campo eligibility_type si falta
            $field = new xmldb_field('eligibility_type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'open', 'value');
            if (!$dbman->field_exists($coupontable, $field)) {
                $dbman->add_field($coupontable, $field);
            }
        }

        // -----------------------------------------------------------------
        // 4. TABLA enrol_mercadopago_coupon_cohorts: crear
        // -----------------------------------------------------------------
        $cohortstable = new xmldb_table('enrol_mercadopago_coupon_cohorts');

        if (!$dbman->table_exists($cohortstable)) {
            $cohortstable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $cohortstable->add_field('couponid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $cohortstable->add_field('cohortid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $cohortstable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $cohortstable->add_key('couponid_fk', XMLDB_KEY_FOREIGN, ['couponid'], 'enrol_mercadopago_coupons', ['id']);
            $cohortstable->add_key('cohortid_fk', XMLDB_KEY_FOREIGN, ['cohortid'], 'cohort', ['id']);
            $cohortstable->add_index('couponid_cohortid_uix', XMLDB_INDEX_UNIQUE, ['couponid', 'cohortid']);

            $dbman->create_table($cohortstable);
        }

        // -----------------------------------------------------------------
        // 5. TABLA enrol_mercadopago_coupon_usage: crear
        // -----------------------------------------------------------------
        $usagetable = new xmldb_table('enrol_mercadopago_coupon_usage');

        if (!$dbman->table_exists($usagetable)) {
            $usagetable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $usagetable->add_field('couponid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $usagetable->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $usagetable->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $usagetable->add_field('paymentid', XMLDB_TYPE_CHAR, '64', null, null, null, null);
            $usagetable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $usagetable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $usagetable->add_key('couponid_fk', XMLDB_KEY_FOREIGN, ['couponid'], 'enrol_mercadopago_coupons', ['id']);
            $usagetable->add_index('couponid_userid_uix', XMLDB_INDEX_UNIQUE, ['couponid', 'userid']);
            $usagetable->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);

            $dbman->create_table($usagetable);
        }

        // Guardar savepoint
        upgrade_plugin_savepoint(true, 2025012800, 'enrol', 'mercadopago');
    }

    return true;
}
