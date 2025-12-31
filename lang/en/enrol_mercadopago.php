<?php
// This file is part of Moodle - http://moodle.org/.
//
// MercadoPago enrolment plugin language file
// @package    enrol_mercadopago
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

$string['pluginname_link'] = 'enrol/mercadopago';
$string['pluginname'] = 'MercadoPago enrolment';
$string['pluginname_desc'] = 'Allows paid course enrolments using MercadoPago integration. Users can purchase course access using MercadoPago checkout.';

$string['accesstoken'] = 'Access Token';
$string['accesstoken_desc'] = 'Enter your MercadoPago API Access Token obtained from your developer account.';
$string['publickey'] = 'Public Key';
$string['publickey_desc'] = 'Enter your MercadoPago Public Key obtained from your developer account.';

$string['cost'] = 'Course cost';
$string['cost_desc'] = 'Default enrolment cost for courses. The course setting overrides this default value.';
$string['costerror'] = 'The enrolment cost must be a valid number.';
$string['currency'] = 'Currency';
$string['currency_desc'] = 'Select the default currency for MercadoPago payments.';

$string['status'] = 'Allow MercadoPago enrolments';
$string['status_desc'] = 'If enabled, users can enrol into courses via MercadoPago checkout.';

$string['defaultrole'] = 'Default role assignment';
$string['defaultrole_desc'] = 'Select the role assigned to users when they enrol via MercadoPago.';

$string['enrolperiod'] = 'Enrolment duration';
$string['enrolperiod_desc'] = 'Length of time the enrolment is valid. If set to zero, enrolment duration is unlimited.';
$string['enrolstartdate'] = 'Start date';
$string['enrolstartdate_help'] = 'If enabled, users can enrol only after this date.';
$string['enrolenddate'] = 'End date';
$string['enrolenddate_help'] = 'If enabled, users can enrol until this date only.';
$string['enrolenddaterror'] = 'End date cannot be earlier than start date.';

$string['assignrole'] = 'Assign role';
$string['expiredaction'] = 'Enrolment expiry action';
$string['expiredaction_help'] = 'Select what happens when the enrolment expires.';

$string['mailadmins'] = 'Notify site administrators';
$string['mailstudents'] = 'Notify enrolled students';
$string['mailteachers'] = 'Notify teachers';
$string['messageprovider:mercadopago_enrolment'] = 'MercadoPago enrolment messages';

$string['nocost'] = 'There is no cost associated with enrolling in this course!';
$string['sendpaymentbutton'] = 'Proceed to payment via MercadoPago';
$string['paymentthanks'] = 'Thank you for your payment! Your enrolment has been confirmed.';
$string['paymentpending'] = 'Your MercadoPago payment is pending confirmation.';
$string['paymentfailed'] = 'The payment could not be processed. Please try again or contact the administrator.';

$string['transactions'] = 'MercadoPago transactions';
$string['processexpirationstask'] = 'MercadoPago enrolment send expiry notifications task';
$string['unenrolselfconfirm'] = 'Do you really want to unenrol yourself from course "{$a}"?';

$string['errdisabled'] = 'The MercadoPago enrolment plugin is disabled and cannot handle payment notifications.';
$string['erripninvalid'] = 'Instant payment notification could not be verified by MercadoPago.';
$string['errconnect'] = 'Could not connect to {$a->url} to verify the payment notification: {$a->result}.';

$string['debug'] = 'Debug mode';
$string['debug_desc'] = 'If enabled, payment requests and responses will be logged in Moodle debug logs.';

$string['privacy:metadata:enrol_mercadopago'] = 'Information stored about MercadoPago transactions for enrolments.';
$string['privacy:metadata:enrol_mercadopago:userid'] = 'The ID of the user who purchased course access.';
$string['privacy:metadata:enrol_mercadopago:courseid'] = 'The ID of the course purchased.';
$string['privacy:metadata:enrol_mercadopago:paymentid'] = 'MercadoPago transaction ID.';
$string['privacy:metadata:enrol_mercadopago:payment_status'] = 'The payment status.';
$string['privacy:metadata:enrol_mercadopago:amount'] = 'Amount paid by the user.';
$string['privacy:metadata:enrol_mercadopago:currency'] = 'Currency of the payment.';
$string['privacy:metadata:enrol_mercadopago:timecreated'] = 'The date/time the payment was recorded.';

$string['mercadopago:config'] = 'Configure MercadoPago enrolment instances';
$string['mercadopago:manage'] = 'Manage enrolled users';
$string['mercadopago:unenrol'] = 'Unenrol users from course';
$string['mercadopago:unenrolself'] = 'Unenrol self from the course';

$string['login_or_register'] = 'Log in or register to continue';
$string['email_payment_confirmation'] = '
<p>Hello {$a->firstname},</p>
<p>Your payment for the course <strong>{$a->coursename}</strong> has been confirmed.</p>
<p>Payment method: {$a->method}<br>Amount paid: {$a->amount}</p>
<p>You can now access the course from your account.</p>';

$string['alreadyenrolled'] = 'You are already enrolled in this course.';

// --- Return page messages ---
$string['return_success_title'] = 'Payment confirmed successfully!';
$string['return_success_message'] = 'Your payment was processed and your enrolment has been completed.';
$string['return_pending_title'] = 'Payment pending approval';
$string['return_pending_message'] = 'Your payment has not yet been confirmed by MercadoPago. You will be notified once it is approved.';
$string['return_failure_title'] = 'There was a problem with your payment';
$string['return_failure_message'] = 'The payment was not processed or was cancelled. Please try again from the course page.';
$string['return_redirecting'] = 'You will be redirected automatically in a few seconds...';

$string['tasksyncenrolments'] = 'Sync pending Mercado Pago enrolments';

$string['reportmercadopago'] = 'Mercado Pago Payments';
$string['tasksyncenrolments'] = 'Sync pending Mercado Pago enrolments';
$string['reprocessenrol'] = 'Reprocess enrolment';
$string['exportcsv'] = 'Export to CSV';

$string['enrolperiod_help'] = 'The period of time that the enrolment remains active, starting from the moment the user is enrolled. If disabled, the enrolment will not expire.';
$string['enrolstartdate_help'] = 'If enabled, users can enrol in the course starting from this date.';
$string['enrolenddate_help'] = 'If enabled, users can enrol in the course until this date only.';

$string['brandprimary'] = 'Color primario (branding)';
$string['brandheaderbg'] = 'Color de cabecera en correos';
$string['brandlogo'] = 'Logo para correos (URL)';

$string['managecoupons'] = 'Gestión de cupones (Mercado Pago)';
