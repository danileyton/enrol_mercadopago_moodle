<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/enrol/mercadopago/classes/util.php');

use enrol_mercadopago\util;

require_login();
admin_externalpage_setup('reportmercadopago');

$context = context_system::instance();
require_capability('moodle/site:config', $context);

global $DB;

// --- Obtener registros ---
$records = $DB->get_records_sql("
    SELECT mp.*, c.fullname AS coursename,
           u.firstname, u.lastname, u.email,
           u.firstnamephonetic, u.lastnamephonetic,
           u.middlename, u.alternatename,
           CASE WHEN ue.id IS NULL THEN 0 ELSE 1 END AS enrolled
      FROM {enrol_mercadopago} mp
      JOIN {course} c ON c.id = mp.courseid
      JOIN {user} u ON u.id = mp.userid
 LEFT JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.enrolid = mp.instanceid
  ORDER BY mp.timecreated DESC
");

$PAGE->set_title('Pagos Mercado Pago');
$PAGE->set_heading('Reporte de transacciones con Mercado Pago');
echo $OUTPUT->header();
echo $OUTPUT->heading('Reporte de transacciones Mercado Pago');

if (empty($records)) {
    echo html_writer::div('No hay pagos registrados todavía.', 'alert alert-info');
    echo $OUTPUT->footer();
    exit;
}
?>

<!-- 📦 Librerías DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<!-- 🔍 Filtros -->
<div class="row mb-3">
  <div class="col-md-3">
    <label class="form-label fw-bold">📘 Curso:</label>
    <select id="filterCourse" class="form-select">
      <option value="">Todos</option>
      <?php
      $courses = $DB->get_records_sql("SELECT DISTINCT c.fullname FROM {enrol_mercadopago} mp JOIN {course} c ON c.id = mp.courseid ORDER BY c.fullname ASC");
      foreach ($courses as $c) echo '<option value="' . s($c->fullname) . '">' . s($c->fullname) . '</option>';
      ?>
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label fw-bold">⚙️ Estado:</label>
    <select id="filterStatus" class="form-select">
      <option value="">Todos</option>
      <option value="approved">Aprobado</option>
      <option value="pending">Pendiente</option>
      <option value="failed">Fallido</option>
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label fw-bold">🔄 Confirmado por:</label>
    <select id="filterConfirmed" class="form-select">
      <option value="">Todos</option>
      <option value="RETURN">Return</option>
      <option value="IPN">IPN</option>
    </select>
  </div>
</div>

<!-- 📋 Tabla -->
<div class="table-responsive mt-4">
<table id="mpreport" class="table table-striped table-hover align-middle shadow-sm" style="width:100%">
  <thead class="table-light">
    <tr>
      <th>ID Pago</th>
      <th>Usuario</th>
      <th>Email</th>
      <th>Curso</th>
      <th>Monto</th>
      <th>Estado</th>
      <th>Confirmado por</th>
      <th>Matriculado</th>
      <th>Fecha</th>
      <th>Cupón</th>
      <th>Descuento</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($records as $r):
      $userurl = new moodle_url('/user/profile.php', ['id' => $r->userid]);
      
      $statusclass = match ($r->status) {
          'approved' => 'text-success fw-bold',
          'pending' => 'text-warning fw-bold',
          'failed', 'cancelled' => 'text-danger fw-bold',
          default => 'text-secondary',
      };
      
      $coupon = $r->couponcode ?? '—';
      $discount = $r->discount ? '$' . number_format($r->discount, 0, ',', '.') : '—';
  ?>
  <tr>
    <td><?= s($r->paymentid) ?></td>
    <td><a href="<?= $userurl ?>"><?= fullname($r) ?></a></td>
    <td><?= s($r->email) ?></td>
    <td><?= s($r->coursename) ?></td>
    <td class="text-end"><?= ($r->currency ?? 'CLP') . ' ' . number_format((float)($r->amount ?? 0), 0, ',', '.') ?></td>
    <td class="<?= $statusclass ?>"><?= ucfirst($r->status ?? '-') ?></td>
    <td><?= s($r->confirmedby ?? 'RETURN') ?></td>
    <td><?= $r->enrolled ? '✅ Sí' : '❌ No' ?></td>
    <td><?= userdate($r->timecreated, '%Y-%m-%d %H:%M') ?></td>
    <td><?= s($coupon) ?></td>
    <td class="text-end"><?= s($discount) ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<!-- ⚙️ Configuración DataTable -->
<script>
$(document).ready(function() {
  const table = $('#mpreport').DataTable({
    pageLength: 10,
    responsive: true,
    order: [[8, 'desc']],
    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
    dom: 'Bfrtip',
    buttons: [
      { extend: 'excelHtml5', text: '📘 Exportar Excel', className: 'btn btn-success btn-sm', title: 'Reporte MercadoPago' },
      { extend: 'pdfHtml5', text: '📄 Exportar PDF', className: 'btn btn-danger btn-sm', title: 'Reporte MercadoPago' }
    ],
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' }
  });

  $('#filterCourse').on('change', function() { table.column(3).search(this.value).draw(); });
  $('#filterStatus').on('change', function() { table.column(5).search(this.value).draw(); });
  $('#filterConfirmed').on('change', function() { table.column(6).search(this.value).draw(); });
});
</script>

<?php echo $OUTPUT->footer();
