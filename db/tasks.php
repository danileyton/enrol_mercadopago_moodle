<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'enrol_mercadopago\\task\\check_pending_payments_task',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ]
];
