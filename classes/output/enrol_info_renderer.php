<?php
namespace enrol_mercadopago\output;

defined('MOODLE_INTERNAL') || die();

use moodle_url;
use renderable;
use renderer_base;
use templatable;
use stdClass;

class enrol_info_renderer implements renderable, templatable {
    private $course;

    public function __construct($course) {
        $this->course = $course;
    }

    public function export_for_template(renderer_base $output) {
        global $USER, $DB;

        $enrolinstances = enrol_get_instances($this->course->id, false);
        $cost = 0;
        $currency = '';

        foreach ($enrolinstances as $instance) {
            if ($instance->enrol === 'mercadopago') {
                $cost = $instance->cost ?? 0;
                $currency = $instance->currency ?? 'CLP';
                break;
            }
        }

        $data = new stdClass();
        $data->coursename = format_string($this->course->fullname);
        $data->cost = number_format($cost, 0, ',', '.')." {$currency}";
        $data->haslogin = isloggedin() && !isguestuser();
        $data->payurl = new moodle_url('/enrol/mercadopago/login_signup.php', ['id' => $this->course->id]);

        return $data;
    }
}
