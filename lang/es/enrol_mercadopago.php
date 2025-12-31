<?php
// Archivo de idioma en español - enrol_mercadopago
// @package    enrol_mercadopago

$string['pluginname'] = 'Matrícula con Mercado Pago';
$string['pluginname_desc'] = 'Permite matricularse en cursos de pago mediante la integración con Mercado Pago. Los usuarios pueden adquirir acceso al curso a través del checkout de Mercado Pago.';

$string['accesstoken'] = 'Token de acceso';
$string['accesstoken_desc'] = 'Introduce tu Access Token obtenido desde tu cuenta de desarrollador de Mercado Pago.';
$string['publickey'] = 'Clave pública';
$string['publickey_desc'] = 'Introduce tu Public Key obtenida desde tu cuenta de desarrollador de Mercado Pago.';

$string['cost'] = 'Costo del curso';
$string['cost_desc'] = 'Costo predeterminado de matrícula. El valor configurado en el curso tiene prioridad.';
$string['currency'] = 'Moneda';
$string['currency_desc'] = 'Selecciona la moneda predeterminada para los pagos con Mercado Pago.';

$string['status'] = 'Habilitar matrícula con Mercado Pago';
$string['status_desc'] = 'Si está habilitado, los usuarios podrán matricularse mediante el checkout de Mercado Pago.';

$string['defaultrole'] = 'Rol predeterminado';
$string['defaultrole_desc'] = 'Selecciona el rol asignado al usuario cuando se matricula mediante Mercado Pago.';

$string['nocost'] = '¡Este curso no tiene costo de matrícula!';
$string['sendpaymentbutton'] = 'Proceder al pago con Mercado Pago';
$string['alreadyenrolled'] = 'Ya estás matriculado en este curso.';

$string['paymentthanks'] = '¡Gracias por tu pago! Tu matrícula ha sido confirmada.';
$string['paymentpending'] = 'Tu pago está pendiente de confirmación.';
$string['paymentfailed'] = 'El pago no se procesó correctamente. Intenta nuevamente o contacta al administrador.';

// --- Mensajes de retorno ---
$string['return_success_title'] = '¡Pago confirmado con éxito!';
$string['return_success_message'] = 'Tu pago fue procesado correctamente y tu matrícula ha sido completada.';
$string['return_pending_title'] = 'Pago pendiente de aprobación';
$string['return_pending_message'] = 'Tu pago aún no ha sido confirmado por Mercado Pago. Recibirás un correo cuando sea aprobado.';
$string['return_failure_title'] = 'Ocurrió un problema con tu pago';
$string['return_failure_message'] = 'El pago no fue procesado o fue cancelado. Puedes intentarlo nuevamente desde la página del curso.';
$string['return_redirecting'] = 'Serás redirigido automáticamente en unos segundos...';

$string['tasksyncenrolments'] = 'Sincronizar matrículas pendientes de Mercado Pago';

$string['reportmercadopago'] = 'Pagos Mercado Pago';
$string['tasksyncenrolments'] = 'Sincronizar matrículas pendientes de Mercado Pago';
$string['reprocessenrol'] = 'Reprocesar matrícula';
$string['exportcsv'] = 'Exportar a CSV';

// Ayudas para el formulario de instancia del curso
$string['enrolperiod_help'] = 'Duración del tiempo que la matrícula permanecerá activa desde el momento en que el usuario se inscribe. Si está deshabilitado, la matrícula será indefinida.';
$string['enrolstartdate_help'] = 'Si se habilita, los usuarios podrán matricularse en el curso a partir de esta fecha.';
$string['enrolenddate_help'] = 'Si se habilita, los usuarios podrán matricularse hasta esta fecha. No podrán hacerlo después.';

$string['brandprimary'] = 'Color primario (branding)';
$string['brandheaderbg'] = 'Color de cabecera en correos';
$string['brandlogo'] = 'Logo para correos (URL)';

$string['managecoupons'] = 'Gestión de cupones (Mercado Pago)';

