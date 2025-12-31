<?php
/**
 * Gestión de cupones con fechas de inicio, fin y número máximo de usos
 * @package    enrol_mercadopago
 */

require('../../config.php');
require_login();

$courseid = required_param('id', PARAM_INT);
$action   = optional_param('action', '', PARAM_TEXT);
$editid   = optional_param('editid', 0, PARAM_INT);

$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);

$PAGE->set_url(new moodle_url('/enrol/mercadopago/manage_coupons.php', ['id' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Gestión de Cupones');
$PAGE->set_heading('Gestión de Cupones de Descuento (Mercado Pago)');

global $DB, $OUTPUT;

// -----------------------------------------------------------------------------
// FUNCIONES AUXILIARES
// -----------------------------------------------------------------------------
function timestamp_from_datefield($datefield) {
    return $datefield ? strtotime($datefield . ' 23:59:59') : 0;
}

function validate_coupon_dates($validfrom, $validuntil) {
    return (!$validfrom || !$validuntil || $validuntil >= $validfrom);
}

// -----------------------------------------------------------------------------
// ACCIONES CRUD
// -----------------------------------------------------------------------------

// Crear cupón nuevo.
if ($action === 'add' && confirm_sesskey()) {
    $validfrom = timestamp_from_datefield(optional_param('validfrom', '', PARAM_TEXT));
    $validuntil = timestamp_from_datefield(optional_param('validuntil', '', PARAM_TEXT));

    if (!validate_coupon_dates($validfrom, $validuntil)) {
        redirect($PAGE->url, '❌ La fecha de fin no puede ser anterior a la fecha de inicio.', null, \core\output\notification::NOTIFY_ERROR);
    }

    $data = (object)[
        'courseid'   => $courseid,
        'code'       => strtoupper(trim(required_param('code', PARAM_TEXT))),
        'type'       => required_param('type', PARAM_TEXT),
        'value'      => required_param('value', PARAM_FLOAT),
        'validfrom'  => $validfrom,
        'validuntil' => $validuntil,
        'active'     => optional_param('active', 1, PARAM_INT),
        'maxuses'    => optional_param('maxuses', 0, PARAM_INT),
        'usedcount'  => 0,
        'timecreated' => time(),
        'timemodified' => time()
    ];

    $DB->insert_record('enrol_mercadopago_coupons', $data);
    redirect($PAGE->url, '✅ Cupón creado correctamente.');
}

// Actualizar cupón existente.
if ($action === 'update' && confirm_sesskey()) {
    $validfrom = timestamp_from_datefield(optional_param('validfrom', '', PARAM_TEXT));
    $validuntil = timestamp_from_datefield(optional_param('validuntil', '', PARAM_TEXT));

    if (!validate_coupon_dates($validfrom, $validuntil)) {
        redirect($PAGE->url, '❌ La fecha de fin no puede ser anterior a la fecha de inicio.', null, \core\output\notification::NOTIFY_ERROR);
    }

    $data = (object)[
        'id'          => required_param('idcoupon', PARAM_INT),
        'courseid'    => $courseid,
        'code'        => strtoupper(trim(required_param('code', PARAM_TEXT))),
        'type'        => required_param('type', PARAM_TEXT),
        'value'       => required_param('value', PARAM_FLOAT),
        'validfrom'   => $validfrom,
        'validuntil'  => $validuntil,
        'active'      => optional_param('active', 1, PARAM_INT),
        'maxuses'     => optional_param('maxuses', 0, PARAM_INT),
        'timemodified'=> time()
    ];

    $DB->update_record('enrol_mercadopago_coupons', $data);
    redirect($PAGE->url, '✏️ Cupón actualizado correctamente.');
}

// Eliminar cupón existente.
if ($action === 'delete' && confirm_sesskey()) {
    $deleteid = required_param('deleteid', PARAM_INT);
    $DB->delete_records('enrol_mercadopago_coupons', ['id' => $deleteid, 'courseid' => $courseid]);
    redirect($PAGE->url, '🗑️ Cupón eliminado correctamente.');
}

// Obtener cupón en modo edición (si existe)
$editcoupon = null;
if ($editid) {
    $editcoupon = $DB->get_record('enrol_mercadopago_coupons', ['id' => $editid, 'courseid' => $courseid]);
}

// Listado actual.
$coupons = $DB->get_records('enrol_mercadopago_coupons', ['courseid' => $courseid], 'timecreated DESC');

// -----------------------------------------------------------------------------
// RENDER
// -----------------------------------------------------------------------------
echo $OUTPUT->header();
echo $OUTPUT->heading('Gestión de Cupones de Descuento');
?>

<div class="container mt-4 mb-5">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <h5 class="card-title mb-3">
        <?php echo $editcoupon ? '✏️ Editar cupón existente' : '➕ Crear nuevo cupón'; ?>
      </h5>

      <form method="post"
            action="<?php echo $PAGE->url->out(false) . '&action=' . ($editcoupon ? 'update' : 'add'); ?>"
            class="row g-3">

        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <?php if ($editcoupon) : ?>
            <input type="hidden" name="idcoupon" value="<?php echo $editcoupon->id; ?>">
        <?php endif; ?>

        <div class="col-md-4">
          <label class="form-label fw-bold">Código del cupón</label>
          <input class="form-control" name="code" required maxlength="50"
                 value="<?php echo $editcoupon ? s($editcoupon->code) : ''; ?>"
                 placeholder="EJ: NUEVO10">
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">Tipo de descuento</label>
          <select class="form-select" name="type">
            <option value="percent" <?php if ($editcoupon && $editcoupon->type === 'percent') echo 'selected'; ?>>Porcentaje (%)</option>
            <option value="amount" <?php if ($editcoupon && $editcoupon->type === 'amount') echo 'selected'; ?>>Monto fijo</option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label fw-bold">Valor</label>
          <input class="form-control" name="value" type="number" step="0.01" min="0" required
                 value="<?php echo $editcoupon ? s($editcoupon->value) : ''; ?>"
                 placeholder="Ej: 10">
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">Válido desde</label>
          <input class="form-control" type="date" name="validfrom"
                 value="<?php echo $editcoupon && $editcoupon->validfrom ? date('Y-m-d', $editcoupon->validfrom) : ''; ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">Válido hasta</label>
          <input class="form-control" type="date" name="validuntil"
                 value="<?php echo $editcoupon && $editcoupon->validuntil ? date('Y-m-d', $editcoupon->validuntil) : ''; ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">Número máximo de usos</label>
          <input type="number" name="maxuses" id="maxuses" class="form-control"
                 value="<?php echo $editcoupon ? (int)$editcoupon->maxuses : 0; ?>" min="0">
          <small class="form-text text-muted">0 = ilimitado (sin límite de usos)</small>
        </div>

        <div class="col-md-2">
          <label class="form-label fw-bold">Activo</label>
          <select name="active" class="form-select">
            <option value="1" <?php if (!$editcoupon || $editcoupon->active) echo 'selected'; ?>>Sí</option>
            <option value="0" <?php if ($editcoupon && !$editcoupon->active) echo 'selected'; ?>>No</option>
          </select>
        </div>

        <div class="col-12 mt-3">
          <button type="submit" class="btn btn-success">
            <?php echo $editcoupon ? '💾 Guardar cambios' : '💾 Guardar cupón'; ?>
          </button>
          <?php if ($editcoupon): ?>
            <a href="<?php echo $PAGE->url; ?>" class="btn btn-secondary">❌ Cancelar</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
// --- Tabla de cupones existentes ---
if ($coupons) {
    echo '<div class="container"><div class="card shadow-sm border-0"><div class="card-body">';
    echo '<h5 class="card-title mb-3">🎟️ Cupones actuales</h5>';
    echo '<div class="table-responsive"><table class="table table-striped align-middle">';
    echo '<thead class="table-light">
            <tr>
              <th>Código</th>
              <th>Tipo</th>
              <th>Valor</th>
              <th>Inicio</th>
              <th>Fin</th>
              <th>Usos</th>
              <th>Límite</th>
              <th>Activo</th>
              <th>Acciones</th>
            </tr>
          </thead><tbody>';

    foreach ($coupons as $c) {
        $validfrom = $c->validfrom ? date('d/m/Y', $c->validfrom) : '—';
        $validuntil = $c->validuntil ? date('d/m/Y', $c->validuntil) : '—';
        $active = $c->active ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>';
        $value = $c->type === 'percent'
            ? "{$c->value}%"
            : '$' . number_format($c->value, 0, ',', '.');

        $editurl = new moodle_url($PAGE->url, ['editid' => $c->id]);
        $deleteurl = new moodle_url($PAGE->url, [
            'action' => 'delete',
            'deleteid' => $c->id,
            'sesskey' => sesskey()
        ]);

        $limit = $c->maxuses == 0 ? '∞' : $c->maxuses;
        echo "<tr>
                <td class='fw-bold'>{$c->code}</td>
                <td>{$c->type}</td>
                <td>{$value}</td>
                <td>{$validfrom}</td>
                <td>{$validuntil}</td>
                <td>{$c->usedcount}</td>
                <td>{$limit}</td>
                <td>{$active}</td>
                <td>
                  <a href='{$editurl}' class='btn btn-outline-primary btn-sm'>Editar</a>
                  <a href='{$deleteurl}' class='btn btn-outline-danger btn-sm'
                     onclick=\"return confirm('¿Eliminar el cupón {$c->code}?');\">Eliminar</a>
                </td>
              </tr>";
    }

    echo '</tbody></table></div></div></div></div></div>';
} else {
    echo '<div class="container"><div class="alert alert-info shadow-sm">No hay cupones configurados para este curso.</div></div>';
}

echo $OUTPUT->footer();
