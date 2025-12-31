<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Actualizaciones del plugin enrol_mercadopago.
 *
 * Se encarga de crear/ajustar tablas cuando se actualiza el plugin
 * en una plataforma que ya tenía una versión anterior instalada.
 */
function xmldb_enrol_mercadopago_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // -------------------------------------------------------------------------
    // Versión 2025111700: asegurar tablas enrol_mercadopago y enrol_mercadopago_log
    // -------------------------------------------------------------------------
    if ($oldversion < 2025111700) {

        // === Tabla enrol_mercadopago ===
        $table = new xmldb_table('enrol_mercadopago');

        if (!$dbman->table_exists($table)) {
            // Si la tabla no existe (instalación vieja incompleta), la creamos
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);

            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            // SIN default '' para evitar warning XMLDB: simplemente NOTNULL sin default
            $table->add_field('paymentid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $table->add_field('external_reference', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);

            $table->add_field('status_detail', XMLDB_TYPE_CHAR, '255', null, null, null, '');
            $table->add_field('amount', XMLDB_TYPE_NUMBER, '12,2', null, XMLDB_NOTNULL, null, '0.00');
            $table->add_field('currency', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'CLP');
            $table->add_field('payment_method', XMLDB_TYPE_CHAR, '32', null, null, null, '');
            $table->add_field('payment_type', XMLDB_TYPE_CHAR, '32', null, null, null, '');
            $table->add_field('merchant_order_id', XMLDB_TYPE_CHAR, '64', null, null, null, '');
            $table->add_field('date_created', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('date_approved', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('courseid_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $table->add_index('paymentid_uix', XMLDB_INDEX_UNIQUE, ['paymentid']);

            $dbman->create_table($table);

        } else {
            // Si la tabla existe, aseguramos que los campos faltantes se creen / ajusten

            // Definición de campos: type, length, notnull, default
            $fields = [
                'courseid'          => [XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, '0'],
                'userid'            => [XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, '0'],
                'instanceid'        => [XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, '0'],

                // Para estos CHAR NOT NULL, default = null (sin default explícito)
                'paymentid'         => [XMLDB_TYPE_CHAR, '64', XMLDB_NOTNULL, null],
                'external_reference'=> [XMLDB_TYPE_CHAR, '255', XMLDB_NOTNULL, null],
                'status'            => [XMLDB_TYPE_CHAR, '32', XMLDB_NOTNULL, null],

                'status_detail'     => [XMLDB_TYPE_CHAR, '255', null, ''],
                'amount'            => [XMLDB_TYPE_NUMBER, '12,2', XMLDB_NOTNULL, '0.00'],
                'currency'          => [XMLDB_TYPE_CHAR, '10', XMLDB_NOTNULL, 'CLP'],
                'payment_method'    => [XMLDB_TYPE_CHAR, '32', null, ''],
                'payment_type'      => [XMLDB_TYPE_CHAR, '32', null, ''],
                'merchant_order_id' => [XMLDB_TYPE_CHAR, '64', null, ''],
                'date_created'      => [XMLDB_TYPE_INTEGER, '10', null, '0'],
                'date_approved'     => [XMLDB_TYPE_INTEGER, '10', null, '0'],
                'timecreated'       => [XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, '0'],
            ];

            foreach ($fields as $name => $def) {
                [$type, $len, $notnull, $default] = $def;
                $field = new xmldb_field($name);

                if (!$dbman->field_exists($table, $field)) {
                    // Crear campo
                    if ($default === null) {
                        // Sin default explícito
                        $field->set_attributes($type, $len, null, $notnull);
                    } else {
                        $field->set_attributes($type, $len, null, $notnull, null, null, null, $default);
                    }
                    $dbman->add_field($table, $field);
                } else {
                    // Podrías ajustar aquí NOTNULL/DEFAULT si quisieras, pero no es imprescindible
                }
            }

            // Índice único por paymentid
            $index = new xmldb_index('paymentid_uix', XMLDB_INDEX_UNIQUE, ['paymentid']);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        // === Tabla enrol_mercadopago_log ===
        $logtable = new xmldb_table('enrol_mercadopago_log');
        if (!$dbman->table_exists($logtable)) {
            $logtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $logtable->add_field('message', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $logtable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $logtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $dbman->create_table($logtable);
        }

        // Guardar savepoint
        upgrade_plugin_savepoint(true, 2025111700, 'enrol', 'mercadopago');
    }

    return true;
}
