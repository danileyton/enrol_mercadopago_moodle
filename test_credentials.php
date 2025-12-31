<?php
// ============================================================================
// test_credentials.php
// Verificación universal de credenciales Mercado Pago (SDK v3+)
// Compatible con cualquier versión de DX-PHP (sin dependencias internas)
// ============================================================================

require_once(__DIR__ . '/../../config.php');

require_login();
if (!is_siteadmin()) {
    die('⚠️ Solo los administradores pueden ejecutar esta prueba.');
}

echo "<h2>🧪 Verificación de credenciales de Mercado Pago</h2>";
require_once(__DIR__ . '/vendor/autoload.php');

$publickey   = get_config('enrol_mercadopago', 'publickey');
$accesstoken = get_config('enrol_mercadopago', 'accesstoken');

if (empty($publickey) || empty($accesstoken)) {
    echo "<p>❌ No hay credenciales configuradas en el plugin.</p>";
    echo "<p>Configúralas en:</p>";
    echo "<code>Administración del sitio → Plugins → Matriculaciones → Mercado Pago</code>";
    exit;
}

echo "<p>🔑 Public key: <code>{$publickey}</code></p>";
echo "<p>🔐 Access token: <code>" . substr($accesstoken, 0, 20) . "********</code></p>";

// --- Verificar token mediante llamada directa a la API Mercado Pago ---
$url = "https://api.mercadopago.com/users/me";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$accesstoken}",
        "Content-Type: application/json"
    ],
    CURLOPT_TIMEOUT => 15,
]);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

echo "<hr>";

if ($error) {
    echo "<h3>❌ Error de conexión cURL</h3>";
    echo "<pre>{$error}</pre>";
    exit;
}

$data = json_decode($response, true);

if ($httpcode === 200 && isset($data['id'])) {
    echo "<h3>✅ Credenciales válidas</h3>";
    echo "<ul>";
    echo "<li><strong>ID de cuenta:</strong> {$data['id']}</li>";
    echo "<li><strong>Nombre / Nick:</strong> {$data['nickname']}</li>";
    echo "<li><strong>Email:</strong> {$data['email']}</li>";
    echo "<li><strong>Site ID:</strong> {$data['site_id']}</li>";
    echo "<li><strong>Tipo de usuario:</strong> {$data['user_type']}</li>";
    echo "</ul>";

    if ($data['site_id'] !== 'MLC') {
        echo "<p style='color:orange;'>⚠️ Advertencia: este token pertenece a otro país. Site ID: <strong>{$data['site_id']}</strong>.</p>";
        echo "<p>Debes usar credenciales de una cuenta de <strong>Mercado Pago Chile</strong>.</p>";
    } else {
        echo "<p>🎯 Todo correcto. Credenciales de Mercado Pago Chile (MLC).</p>";
    }
} else {
    echo "<h3>❌ No se pudieron validar las credenciales</h3>";
    echo "<p><strong>Código HTTP:</strong> {$httpcode}</p>";
    echo "<pre>" . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
}

echo "<hr><p>🕒 Finalizado: " . date('Y-m-d H:i:s') . "</p>";
