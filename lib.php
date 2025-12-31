<?php
// This file is part of Moodle - http://moodle.org/.
//
// MercadoPago enrolment plugin for Moodle 5.0+
// @package    enrol_mercadopago
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;
class enrol_mercadopago_plugin extends enrol_plugin {
    /** Supported currencies. */
    public function get_currencies() {
        $codes = ['ARS', 'BRL', 'CLP', 'COP', 'MXN', 'PEN', 'USD'];
        $currencies = [];
        foreach ($codes as $c) {
            $currencies[$c] = new lang_string($c, 'core_currencies');
        }
        return $currencies;
    }

    public function roles_protected() { return false; }
    public function allow_unenrol(stdClass $instance) { return true; }
    public function allow_manage(stdClass $instance) { return true; }
    public function show_enrolme_link(stdClass $instance) {
        return ($instance->status == ENROL_INSTANCE_ENABLED);
    }
    public function can_add_instance($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);
        return has_capability('enrol/mercadopago:config', $context);
    }
    public function use_standard_editing_ui() {
        return true;
    }

    public function add_instance($course, array $fields = null) {
        if ($fields && !empty($fields['cost'])) {
            $fields['cost'] = unformat_float($fields['cost']);
        }
        return parent::add_instance($course, $fields);
    }
    public function update_instance($instance, $data) {
        if ($data) {
            $data->cost = unformat_float($data->cost);
        }
        return parent::update_instance($instance, $data);
    }

    /**
     * This method is called by Moodle to display enrolment options.
     * We delegate rendering to enrol.html to support guests and logged-in users.
     */
    public function enrol_page_hook(stdClass $instance) {
        ob_start();
        include(__DIR__ . '/enrol.html');
        return ob_get_clean();
    }

    /**
     * Render the entry explicitly (used by course public view).
     */
    public function enrol_mercadopago_print_entry($instance) {
        global $OUTPUT;
        include(__DIR__ . '/enrol.html');
    }

    /**
     * Add/edit instance form for admin.
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {
        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        $statusoptions = [ENROL_INSTANCE_ENABLED => get_string('yes'), ENROL_INSTANCE_DISABLED => get_string('no')];
        $mform->addElement('select', 'status', get_string('status', 'enrol_mercadopago'), $statusoptions);
        $mform->setDefault('status', $this->get_config('status'));

        $mform->addElement('text', 'cost', get_string('cost', 'enrol_mercadopago'), ['size' => 4]);
        $mform->setType('cost', PARAM_RAW);
        $mform->setDefault('cost', format_float($this->get_config('cost'), 2, true));

        $currencies = $this->get_currencies();
        $mform->addElement('select', 'currency', get_string('currency', 'enrol_mercadopago'), $currencies);
        $mform->setDefault('currency', $this->get_config('currency'));

        $roles = get_default_enrol_roles($context, $this->get_config('roleid'));
        $mform->addElement('select', 'roleid', get_string('defaultrole', 'enrol_mercadopago'), $roles);
        $mform->setDefault('roleid', $this->get_config('roleid'));

        $mform->addElement('duration', 'enrolperiod', get_string('enrolperiod', 'enrol_mercadopago'), ['optional' => true]);
        $mform->setDefault('enrolperiod', $this->get_config('enrolperiod'));
        $mform->addHelpButton('enrolperiod', 'enrolperiod', 'enrol_mercadopago');

        $mform->addElement('date_time_selector', 'enrolstartdate', get_string('enrolstartdate', 'enrol_mercadopago'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'enrolenddate', get_string('enrolenddate', 'enrol_mercadopago'), ['optional' => true]);
    }
    public function edit_instance_validation($data, $files, $instance, $context) {
        $errors = [];
        if (!empty($data['enrolenddate']) && $data['enrolenddate'] < $data['enrolstartdate']) {
            $errors['enrolenddate'] = get_string('enrolenddaterror', 'enrol_mercadopago');
        }

        if (!is_numeric(str_replace(',', '.', $data['cost']))) {
            $errors['cost'] = get_string('costerror', 'enrol_mercadopago');
        }
        return $errors;
    }

    /** Allow deleting instance from course UI. */
    public function can_delete_instance($instance) {
        global $USER;
        if (is_siteadmin($USER)) {
            return true;
        }
        $context = context_course::instance($instance->courseid);
        return has_capability('moodle/course:enrolconfig', $context);
    }

    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/mercadopago:config', $context);
    }

    /**
     * Processes a manual enrolment if needed (used by IPN and callbacks).
     */

    public function enrol_mercadopago_process_enrolment($userid, $courseid, $instanceid) {
        global $DB;
        $enrol = enrol_get_plugin('mercadopago');
        $instance = $DB->get_record('enrol', ['id' => $instanceid, 'enrol' => 'mercadopago']);
        if ($instance && $enrol) {
            $enrol->enrol_user($instance, $userid, $instance->roleid, time());
        }
    }
    
}

/**
 * Añade el enlace "Gestión de cupones (Mercado Pago)" al menú del curso (Moodle 5.0+).
 * Compatible con Boost, RemUI y Edwiser.
 */
function enrol_mercadopago_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context) {
    // Mostrar solo a profesores o administradores.
    if (!has_capability('moodle/course:update', $context)) {
        return;
    }

    // URL del panel de gestión de cupones.
    $url = new moodle_url('/enrol/mercadopago/manage_coupons.php', ['id' => $course->id]);

    // Ícono azul (usa pix/icon.svg del plugin si existe).
    $icon = new pix_icon('icon', 'Mercado Pago', 'enrol_mercadopago', ['class' => 'icon', 'style' => 'color:#005BAA;']);

    // Crear el nodo de menú.
    $node = navigation_node::create(
        get_string('managecoupons', 'enrol_mercadopago'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'mercadopago_manage_coupons',
        $icon
    );

    // Agregar el nodo al menú de curso.
    if (method_exists($navigation, 'add_node')) {
        $navigation->add_node($node);
    } elseif (method_exists($navigation, 'add')) {
        $navigation->add($node);
    }
}
