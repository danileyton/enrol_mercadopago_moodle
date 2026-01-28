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


// --- Mensajes de cupones ---
$string['coupon_empty'] = 'Por favor ingresa un código de cupón.';
$string['coupon_invalid'] = '❌ Cupón no válido. Verifica el código e intenta nuevamente.';
$string['coupon_inactive'] = '❌ Este cupón ya no está disponible.';
$string['coupon_not_started'] = '⏳ Este cupón aún no está vigente.';
$string['coupon_expired'] = '⏰ Este cupón ha expirado.';
$string['coupon_exhausted'] = '🎫 Este cupón ha alcanzado su límite de usos.';
$string['coupon_already_used'] = '⚠️ Ya has utilizado este cupón anteriormente.';
$string['coupon_not_eligible'] = '🚫 No tienes acceso a este cupón. Contacta a soporte si crees que deberías poder usarlo.';
$string['coupon_applied_percent'] = '✅ Cupón aplicado: {$a}% de descuento.';
$string['coupon_applied_amount'] = '✅ Cupón aplicado: ${$a} de descuento.';
$string['coupon_no_cohorts'] = 'Este cupón no tiene grupos de elegibilidad configurados.';

// --- Gestión de cupones ---
$string['coupon_code'] = 'Código del cupón';
$string['coupon_type'] = 'Tipo de descuento';
$string['coupon_type_percent'] = 'Porcentaje (%)';
$string['coupon_type_amount'] = 'Monto fijo';
$string['coupon_value'] = 'Valor';
$string['coupon_validfrom'] = 'Válido desde';
$string['coupon_validuntil'] = 'Válido hasta';
$string['coupon_maxuses'] = 'Máximo de usos';
$string['coupon_maxuses_help'] = '0 = ilimitado (sin límite de usos)';
$string['coupon_usedcount'] = 'Veces usado';
$string['coupon_active'] = 'Activo';
$string['coupon_eligibility'] = 'Tipo de elegibilidad';
$string['coupon_eligibility_open'] = 'Abierto (todos)';
$string['coupon_eligibility_restricted'] = 'Restringido (cohortes)';
$string['coupon_cohorts'] = 'Cohortes elegibles';
$string['coupon_cohorts_help'] = 'Selecciona las cohortes cuyos miembros podrán usar este cupón.';
$string['coupon_created'] = '✅ Cupón creado correctamente.';
$string['coupon_updated'] = '✏️ Cupón actualizado correctamente.';
$string['coupon_deleted'] = '🗑️ Cupón eliminado correctamente.';
$string['coupon_date_error'] = '❌ La fecha de fin no puede ser anterior a la fecha de inicio.';

// --- Tareas programadas ---
$string['check_pending_payments_task'] = 'Verificar pagos pendientes en Mercado Pago';

// --- Errores y configuración ---
$string['configerror'] = 'Configuración de Mercado Pago incompleta. Contacte a soporte.';
$string['debug'] = 'Modo debug';
$string['debug_desc'] = 'Habilita logs detallados para depuración.';
$string['mailstudents'] = 'Notificar a estudiantes';
$string['mailteachers'] = 'Notificar a profesores';
$string['mailadmins'] = 'Notificar a administradores';

// --- Período de matrícula ---
$string['enrolperiod'] = 'Duración de la matrícula';
$string['enrolperiod_desc'] = 'Tiempo que durará la matrícula después de inscribirse (0 = ilimitado).';
$string['enrolstartdate'] = 'Fecha de inicio';
$string['enrolenddate'] = 'Fecha de fin';
$string['enrolenddaterror'] = 'La fecha de fin no puede ser anterior a la de inicio.';
$string['costerror'] = 'El costo debe ser un número válido.';
