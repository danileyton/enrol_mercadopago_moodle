<?php
require('../../config.php');
require_login(null, false);

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
$PAGE->set_context(context_course::instance($courseid));
$PAGE->set_url(new moodle_url('/enrol/mercadopago/login_signup.php', ['id' => $courseid]));
$PAGE->set_title(get_string('login_or_register', 'enrol_mercadopago'));

$recaptcha_enabled = get_config('auth', 'recaptcha');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('enrol_mercadopago/login_signup', [
    'recaptcha_enabled' => $recaptcha_enabled,
    'loginurl' => (new moodle_url('/login/index.php', ['course' => $courseid]))->out(false),
    'signupurl' => (new moodle_url('/login/signup.php', ['course' => $courseid]))->out(false)
]);
echo $OUTPUT->footer();