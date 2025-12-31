<?php
// ============================================================================
// test_return_simulator.php - Simulador de retorno de Mercado Pago
// Úsalo para probar return.php sin pasar por la pasarela real.
// ============================================================================

require_once(__DIR__ . '/../../config.php');

// --- Configura manualmente el escenario de prueba ---
$userid     = 458;   // ID de usuario Moodle
$courseid   = 2;     // ID de curso
$instanceid = 22;    // ID de la instancia de matrícula mercadopago
$paymentid  = rand(100000000000, 999999999999); // Simula un ID de pago aleatorio

// Crear registro de pago simulado en la base de datos
$record = (object)[
    'courseid'          => $courseid,
    'userid'            => $userid,
    'instanceid'        => $instanceid,
    'paymentid'         => $paymentid,
    'external_reference'=> "{$courseid}-{$userid}-{$instanceid}",
    'status'            => 'pending',
    'status_detail'     => '',
    'amount'            => '1000.00',
    'currency'          => 'CLP',
    'payment_method'    => 'account_money',
    'payment_type'      => 'account_money',
    'merchant_order_id' => 'TEST-' . rand(10000, 99999),
    'date_created'      => time(),
    'date_approved'     => 0,
    'timecreated'       => time()
];
$DB->insert_record('enrol_mercadopago', $record);


// Construir URL de retorno simulada
$returnurl = new moodle_url('/enrol/mercadopago/return.php', [
    'status'           => 'approved',
    'courseid'         => $courseid,
    'instanceid'       => $instanceid,
    'userid'           => $userid,
    'collection_id'    => $paymentid,
    'collection_status'=> 'approved',
    'payment_id'       => $paymentid,
    'status'           => 'approved',
    'payment_type'     => 'account_money',
    'merchant_order_id'=> 'TEST-' . rand(10000, 99999),
    'preference_id'    => 'LOCAL-TEST-' . rand(1000, 9999),
    'site_id'          => 'MLC',
    'processing_mode'  => 'aggregator'
]);

// Mostrar la URL generada
echo "<h3>🔧 Simulador de Retorno Mercado Pago</h3>";
echo "<p>Haz clic en el siguiente enlace para probar el retorno:</p>";
echo "<a href='{$returnurl}' target='_blank'>{$returnurl}</a>";
echo "<br><br><em>Esto simula un pago aprobado y activará el flujo de matrícula.</em>";
