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
 * 
 * CORRECCIÓN CRÍTICA (BUG-001): 
 * - Se crea registro en BD ANTES de crear preferencia en MP
 * - Se usa external_reference único para tracking
 * - Se guarda preference_id para correlación posterior
 * 
 * @package    enrol_mercadopago
 */
class payment_form {

    /**
     * Renderiza el botón y formulario de pago.
     * 
     * @param object $course Objeto del curso
     * @param object $user Objeto del usuario
     * @param object $instance Instancia de enrol
     */
    public static function render_payment_button($course, $user, $instance) {
        global $CFG, $OUTPUT, $DB;

        require_once($CFG->dirroot . '/enrol/mercadopago/vendor/autoload.php');

        $token     = get_config('enrol_mercadopago', 'accesstoken');
        $publickey = get_config('enrol_mercadopago', 'publickey');
        
        if (empty($token) || empty($publickey)) {
            echo \html_writer::div(
                get_string('configerror', 'enrol_mercadopago') ?: 'Configuración de Mercado Pago incompleta. Contacte a soporte.',
                'alert alert-danger mt-3'
            );
            return;
        }

        MercadoPagoConfig::setAccessToken($token);

        $originalcost = (float)$instance->cost;
        $currency     = $instance->currency ?? 'CLP';

        // =====================================================================
        // CORRECCIÓN BUG-001: Crear registro ANTES de crear preferencia
        // =====================================================================
        
        // Generar external_reference único
        $externalref = sprintf('%d-%d-%d-%d', $course->id, $user->id, $instance->id, time());
        
        // Verificar si ya existe un registro pendiente para este usuario/curso/instancia
        $existingrecord = $DB->get_record('enrol_mercadopago', [
            'courseid' => $course->id,
            'userid' => $user->id,
            'instanceid' => $instance->id,
            'status' => 'initiated'
        ]);
        
        if ($existingrecord) {
            // Actualizar el registro existente
            $paymentrecord = $existingrecord;
            $paymentrecord->external_reference = $externalref;
            $paymentrecord->amount = $originalcost;
            $paymentrecord->final_amount = $originalcost;
            $paymentrecord->currency = $currency;
            $paymentrecord->timemodified = time();
            $DB->update_record('enrol_mercadopago', $paymentrecord);
            util::log("🔄 Registro de pago actualizado (id={$paymentrecord->id}, user={$user->id}, course={$course->id})");
        } else {
            // Crear nuevo registro con estado 'initiated'
            $paymentrecord = new stdClass();
            $paymentrecord->courseid = $course->id;
            $paymentrecord->userid = $user->id;
            $paymentrecord->instanceid = $instance->id;
            $paymentrecord->external_reference = $externalref;
            $paymentrecord->status = 'initiated';
            $paymentrecord->amount = $originalcost;
            $paymentrecord->final_amount = $originalcost;
            $paymentrecord->discount = 0;
            $paymentrecord->currency = $currency;
            $paymentrecord->timecreated = time();
            $paymentrecord->timemodified = time();
            
            $paymentrecord->id = $DB->insert_record('enrol_mercadopago', $paymentrecord);
            util::log("✅ Registro de pago creado (id={$paymentrecord->id}, user={$user->id}, course={$course->id}, ref={$externalref})");
        }

        // =====================================================================
        // Crear preferencia en Mercado Pago
        // =====================================================================
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
                "external_reference" => $externalref,
                "metadata" => [
                    "userid"     => $user->id,
                    "courseid"   => $course->id,
                    "instanceid" => $instance->id,
                    "payment_record_id" => $paymentrecord->id
                ],
                "back_urls" => [
                    "success" => $CFG->wwwroot . "/enrol/mercadopago/return.php?status=success&courseid={$course->id}&instanceid={$instance->id}&userid={$user->id}",
                    "failure" => $CFG->wwwroot . "/enrol/mercadopago/return.php?status=failure&courseid={$course->id}&instanceid={$instance->id}&userid={$user->id}",
                    "pending" => $CFG->wwwroot . "/enrol/mercadopago/return.php?status=pending&courseid={$course->id}&instanceid={$instance->id}&userid={$user->id}",
                ],
                "notification_url" => $CFG->wwwroot . "/enrol/mercadopago/ipn.php",
                "auto_return"      => "approved"
            ]);

            // =====================================================================
            // CRÍTICO: Guardar preference_id en el registro
            // =====================================================================
            $paymentrecord->preference_id = $preference->id;
            $paymentrecord->timemodified = time();
            $DB->update_record('enrol_mercadopago', $paymentrecord);
            
            util::log("🎫 Preferencia MP creada (preference_id={$preference->id}, record_id={$paymentrecord->id})");

        } catch (MPApiException $e) {
            util::log("❌ Error creando preferencia inicial: " . $e->getMessage(), 'error');
            echo \html_writer::div('Error de conexión con Mercado Pago. Intenta nuevamente.', 'alert alert-danger mt-3');
            return;
        } catch (\Exception $e) {
            util::log("❌ Error inesperado: " . $e->getMessage(), 'error');
            echo \html_writer::div('Error inesperado. Contacta a soporte.', 'alert alert-danger mt-3');
            return;
        }

        // =====================================================================
        // HTML del formulario de cupón + botón de pago
        // =====================================================================
        echo '<div class="container mt-3 mb-3" style="max-width:480px;">';
        echo '<div class="fw-bold mb-2">¿Tienes un código de descuento?</div>';
        echo '<p>Si tienes un código de descuento ingrésalo acá</p>';
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

        // Script principal con AJAX para cupones
        $validateurl = $CFG->wwwroot . '/enrol/mercadopago/validate_coupon.php';
        $recordid = $paymentrecord->id;
        
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
    const recordId = ' . json_encode($recordid) . ';

    btn.addEventListener("click", function() {
        const code = input.value.trim();
        if (!code) {
            msg.innerHTML = "<div class=\"alert alert-warning py-2\">Por favor ingresa un código.</div>";
            return;
        }
        msg.innerHTML = "<div class=\"text-muted\">Verificando...</div>";
        btn.disabled = true;

        fetch("' . $validateurl . '?courseid=' . $course->id . '&instanceid=' . $instance->id . '&recordid=" + recordId + "&code=" + encodeURIComponent(code))
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            if (data.valid) {
                msg.innerHTML = "<div class=\"alert alert-success py-2\">" + data.message + "</div>";
                total.textContent = new Intl.NumberFormat("es-CL").format(data.amount) + " " + data.currency;
                renderButton(data.preference_id);
            } else {
                msg.innerHTML = "<div class=\"alert alert-danger py-2\">" + data.message + "</div>";
                total.textContent = new Intl.NumberFormat("es-CL").format(original) + " " + currency;
                renderButton("' . $preference->id . '");
            }
        })
        .catch((err) => {
            btn.disabled = false;
            msg.innerHTML = "<div class=\"alert alert-danger py-2\">Error de conexión con el servidor.</div>";
            console.error("Error validando cupón:", err);
        });
    });
});
</script>';
    }
}
