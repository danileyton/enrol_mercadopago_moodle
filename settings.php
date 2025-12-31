<?php
// This file is part of Moodle - http://moodle.org/.
//
// MercadoPago enrolment plugin settings
// @package    enrol_mercadopago
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // --- General description ---
    $settings->add(new admin_setting_heading(
        'enrol_mercadopago_general',
        get_string('pluginname', 'enrol_mercadopago'),
        get_string('pluginname_desc', 'enrol_mercadopago')
    ));

    // --- API credentials ---
    $settings->add(new admin_setting_configtext(
        'enrol_mercadopago/accesstoken',
        get_string('accesstoken', 'enrol_mercadopago'),
        get_string('accesstoken_desc', 'enrol_mercadopago'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_mercadopago/publickey',
        get_string('publickey', 'enrol_mercadopago'),
        get_string('publickey_desc', 'enrol_mercadopago'),
        '',
        PARAM_TEXT
    ));

    // --- Debug mode ---
    $settings->add(new admin_setting_configcheckbox(
        'enrol_mercadopago/debug',
        get_string('debug', 'enrol_mercadopago'),
        get_string('debug_desc', 'enrol_mercadopago'),
        0
    ));

    // --- Notification preferences ---
    $settings->add(new admin_setting_configcheckbox(
        'enrol_mercadopago/mailstudents',
        get_string('mailstudents', 'enrol_mercadopago'),
        '',
        0
    ));
    $settings->add(new admin_setting_configcheckbox(
        'enrol_mercadopago/mailteachers',
        get_string('mailteachers', 'enrol_mercadopago'),
        '',
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_mercadopago/mailadmins',
        get_string('mailadmins', 'enrol_mercadopago'),
        '',
        0
    ));

    // --- Enable/disable by default ---
    $statusoptions = [
        ENROL_INSTANCE_ENABLED => get_string('yes'),
        ENROL_INSTANCE_DISABLED => get_string('no')
    ];

    $settings->add(new admin_setting_configselect(
        'enrol_mercadopago/status',
        get_string('status', 'enrol_mercadopago'),
        get_string('status_desc', 'enrol_mercadopago'),
        ENROL_INSTANCE_DISABLED,
        $statusoptions
    ));

    // --- Default cost ---
    $settings->add(new admin_setting_configtext(
        'enrol_mercadopago/cost',
        get_string('cost', 'enrol_mercadopago'),
        get_string('cost_desc', 'enrol_mercadopago'),
        0,
        PARAM_FLOAT,
        4
    ));

    // --- Currency ---
    $currencies = enrol_get_plugin('mercadopago')->get_currencies();
    $settings->add(new admin_setting_configselect(
        'enrol_mercadopago/currency',
        get_string('currency', 'enrol_mercadopago'),
        get_string('currency_desc', 'enrol_mercadopago'),
        'USD',
        $currencies
    ));
    
    // Color primario (botones, encabezados).
    $settings->add(new admin_setting_configtext(
        'enrol_mercadopago/brandprimary',
        get_string('brandprimary', 'enrol_mercadopago'),
        'Color primario para correos (hex).',
        '#005baa', // puedes usar el color principal de Academia CONAC
        PARAM_RAW_TRIMMED
    ));
    
    // Color de fondo del header.
    $settings->add(new admin_setting_configtext(
        'enrol_mercadopago/brandheaderbg',
        get_string('brandheaderbg', 'enrol_mercadopago'),
        'Color de fondo del encabezado de correos.',
        '#00325a',
        PARAM_RAW_TRIMMED
    ));

// URL del logo (puedes usar el logo de la cabecera de RemUI).
$settings->add(new admin_setting_configtext(
    'enrol_mercadopago/brandlogo',
    get_string('brandlogo', 'enrol_mercadopago'),
    'URL del logo a mostrar en la cabecera de los correos.',
    '',
    PARAM_RAW_TRIMMED
));


    // --- Default role assignment ---
    if (!during_initial_install()) {
        $roles = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect(
            'enrol_mercadopago/roleid',
            get_string('defaultrole', 'enrol_mercadopago'),
            get_string('defaultrole_desc', 'enrol_mercadopago'),
            $student->id,
            $roles
        ));
    }
    
    

    // --- Default enrolment period ---
    $settings->add(new admin_setting_configduration(
        'enrol_mercadopago/enrolperiod',
        get_string('enrolperiod', 'enrol_mercadopago'),
        get_string('enrolperiod_desc', 'enrol_mercadopago'),
        0
    ));
}

// SubmenÃº: Reporte de Pagos
if ($hassiteconfig) {
    $ADMIN->add('enrolments', new admin_category(
        'enrol_mercadopago_cat',
        get_string('pluginname', 'enrol_mercadopago')
    ));

    $ADMIN->add('enrol_mercadopago_cat', new admin_externalpage(
        'reportmercadopago',
        get_string('reportmercadopago', 'enrol_mercadopago'),
        new moodle_url('/enrol/mercadopago/report.php'),
        'moodle/site:config'
    ));
    
}

// =====================================================
// GESTIÓN DE CUPONES - VISIBLE POR CURSO
// =====================================================
 

// Registrar enlace en administración del curso.
if ($ADMIN->locate('courseadministration')) {
    $ADMIN->add('courseadministration', new admin_externalpage(
        'enrol_mercadopago_managecoupons',
        get_string('managecoupons', 'enrol_mercadopago'),
        new moodle_url('/enrol/mercadopago/manage_coupons.php', ['id' => required_param('id', PARAM_INT)]),
        'moodle/course:update'
    ));
}
