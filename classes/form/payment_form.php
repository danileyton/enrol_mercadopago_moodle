<?php
namespace enrol_mercadopago\form;

defined('MOODLE_INTERNAL') || die();

use enrol_mercadopago\util;
use stdClass;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;

/**
 * Formulario principal de pago con Mercado Pago (con soporte de cupones AJAX).
 */
class payment_form {

    /**
     * Renderiza el botón y formulario de pago.
     */
    public static function render_payment_button($course, $user, $instance) {
        global $CFG, $OUTPUT, $DB;

        util::log('DEBUG: ejecutando payment_form.php', 'debug');
        require_once($CFG->dirroot . '/enrol/mercadopago/vendor/autoload.php');

        $token     = get_config('enrol_mercadopago', 'accesstoken');
        $publickey = get_config('enrol_mercadopago', 'publickey');
        if (empty($token) || empty($publickey)) {
            echo \html_writer::div(
                'Configuración de Mercado Pago incompleta. Contacte a soporte.',
                'alert alert-danger mt-3'
            );
            return;
        }

        MercadoPagoConfig::setAccessToken($token);

        $originalcost = (float)$instance->cost;
        $currency     = $instance->currency ?? 'CLP';

        // Crear preferencia inicial (sin descuento)
        $client = new PreferenceClient();
        try {
            $preference = $client->create([
                "items" => [[
                    "title"       => format_string($course->fullname),
                    "quantity"    => 1,
                    "unit_price"  => $originalcost,
                    "currency_id" => $currency
                ]],
                "payer" => [
                    "email"   => $user->email,
                    "name"    => $user->firstname,
                    "surname" => $user->lastname
                ],
                "metadata" => [
                    "userid"     => $user->id,
                    "courseid"   => $course->id,
                    "instanceid" => $instance->id
                ],
                "back_urls" => [
                    "success" => $CFG->wwwroot . "/enrol/mercadopago/return.php?status=success&courseid={$course->id}&instanceid={$instance->id}&userid={$user->id}",
                    "failure" => $CFG->wwwroot . "/enrol/mercadopago/return.php?status=failure&courseid={$course->id}&instanceid={$instance->id}&userid={$user->id}",
                    "pending" => $CFG->wwwroot . "/enrol/mercadopago/return.php?status=pending&courseid={$course->id}&instanceid={$instance->id}&userid={$user->id}",
                ],
                "notification_url" => $CFG->wwwroot . "/enrol/mercadopago/ipn.php",
                "auto_return"      => "approved"
            ]);
        } catch (MPApiException $e) {
            util::log('❌ Error creando preferencia inicial: ' . $e->getMessage(), 'error');
            echo \html_writer::div('Error de conexión con Mercado Pago.', 'alert alert-danger mt-3');
            return;
        }

        // --- HTML del formulario de cupón + botón ---
        echo '<div class="container mt-3 mb-3" style="max-width:480px;">';
        echo '<div class="fw-bold mb-2">' . htmlspecialchars("¿Tienes un código de descuento?", ENT_QUOTES, "UTF-8") . '</div>';
        echo '<p>Si tienes un c&oacute;digo de descuento ingresalo ac&aacute;</p>';
        echo '<div class="input-group mb-2">';
        echo '<input type="text" id="couponcode" class="form-control" placeholder="Ej: ALUMNO10">';
        
        echo '<button id="applycoupon" class="btn btn-outline-primary" type="button">Aplicar</button>';
        echo '</div>';
        echo '<div id="couponmsg"></div>';
        echo '<p class="mt-2"><strong>Total a pagar: </strong><span id="totalamount">'
            . number_format($originalcost, 0, ',', '.') . ' ' . $currency . '</span></p>';
        echo '</div>';

        echo '<script src="https://sdk.mercadopago.com/js/v2"></script>';
        echo '<div id="wallet_container"></div>';

        // --- Script principal ---
        echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    const mp = new MercadoPago("' . $publickey . '", { locale: "es-CL" });
    let preferenceId = "' . $preference->id . '";
    const walletContainer = document.getElementById("wallet_container");

    const renderButton = (id) => {
        walletContainer.innerHTML = "";
        mp.bricks().create("wallet", "wallet_container", {
            initialization: { preferenceId: id },
            customization: { texts: { valueProp: "smart_option" } }
        });
    };
    renderButton(preferenceId);

    const btn   = document.getElementById("applycoupon");
    const input = document.getElementById("couponcode");
    const msg   = document.getElementById("couponmsg");
    const total = document.getElementById("totalamount");
    const original = ' . json_encode($originalcost) . ';
    const currency = ' . json_encode($currency) . ';

    btn.addEventListener("click", function() {
        const code = input.value.trim();
        if (!code) {
            msg.innerHTML = "<div class=\'alert alert-warning py-2\'>Por favor ingresa un código.</div>";
            return;
        }
        msg.innerHTML = "<div class=\'text-muted\'>Verificando...</div>";

        fetch("' . $CFG->wwwroot . '/enrol/mercadopago/validate_coupon.php?courseid=' . $course->id . '&instanceid=' . $instance->id . '&code=" + encodeURIComponent(code))
        .then(res => res.json())
        .then(data => {
            if (data.valid) {
                msg.innerHTML = "<div class=\'alert alert-success py-2\'>" + data.message + "</div>";
                total.textContent = new Intl.NumberFormat("es-CL").format(data.amount) + " " + data.currency;
                renderButton(data.preference_id);
            } else {
                msg.innerHTML = "<div class=\'alert alert-danger py-2\'>" + data.message + "</div>";
                total.textContent = new Intl.NumberFormat("es-CL").format(original) + " " + currency;
                renderButton("' . $preference->id . '"); // vuelve al monto original
            }
        })
        .catch(() => {
            msg.innerHTML = "<div class=\'alert alert-danger py-2\'>" + 
                            "' . htmlspecialchars("Error de conexión con el servidor.", ENT_QUOTES, "UTF-8") . '" + 
                            "</div>";
        });
    });
});
</script>';
    }
}
