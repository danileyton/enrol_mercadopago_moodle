<?php
define('AJAX_SCRIPT', true);
require('../../config.php');
require_login();

$courseid = required_param('courseid', PARAM_INT);
$code = required_param('code', PARAM_TEXT);

require_once(__DIR__ . '/classes/coupon_manager.php');

$coupon = \enrol_mercadopago\coupon_manager::validate_coupon($courseid, $code);

if (!$coupon) {
    echo json_encode(['valid' => false]);
    die();
}

$response = [
    'valid' => true,
    'type' => $coupon->type,
    'value' => $coupon->value,
    'discount_text' => $coupon->type == 'percent'
        ? $coupon->value . '%'
        : '$' . number_format($coupon->value, 2)
];

echo json_encode($response);
die();
