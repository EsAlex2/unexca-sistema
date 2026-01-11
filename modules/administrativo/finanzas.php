<?php
// modules/administrativo/finanzas.php
require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SESSION['rol'] != 'administrador') {
    header('Location: ../../index.php');
    exit();
}

$page_title = 'Gestión Financiera';

$db = new Database();
$conn = $db->getConnection();

// Manejar acciones
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

// Filtros
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$estado = $_GET['estado'] ?? '';
$estudiante_id = $_GET['estudiante_id'] ?? '';
$tipo_concepto = $_GET['tipo_concepto'] ?? '';

// Registrar nuevo pago
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_pago'])) {
    $estudiante_id_post = $_POST['estudiante_id'];
    $concepto = sanitize($_POST['concepto']);
    $monto = floatval($_POST['monto']);
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    $metodo_pago = $_POST['metodo_pago'] ?? 'efectivo';
    $referencia = sanitize($_POST['referencia'] ?? '');
    
    $query = "INSERT INTO pagos (estudiante_id, concepto, monto, fecha_vencimiento, metodo_pago, referencia) 
              VALUES (:estudiante_id, :concepto, :monto, :fecha_vencimiento, :metodo_pago, :referencia)";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':estudiante_id', $estudiante_id_post);
    $stmt->bindParam(':concepto', $concepto);
    $stmt->bindParam(':monto', $monto);
    $stmt->bindParam(':fecha_vencimiento', $fecha_vencimiento);
    $stmt->bindParam(':metodo_pago', $metodo_pago);
    $stmt->bindParam(':referencia', $referencia);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Pago registrado exitosamente';
        $_SESSION['message_type'] = 'success';
        header('Location: finanzas.php');
        exit();
    } else {
        $_SESSION['message'] = 'Error al registrar el pago';
        $_SESSION['message_type'] = 'danger';
    }
}

// Marcar pago como pagado
if ($action == 'marcar_pagado' && $id > 0) {
    $query = "UPDATE pagos SET estado = 'pagado', fecha_pago = CURDATE() WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Pago marcado como pagado';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Error al actualizar el pago';
        $_SESSION['message_type'] = 'danger';
    }
    
    header('Location: finanzas.php');
    exit();
}

// Eliminar pago
if ($action == 'eliminar' && $id > 0) {
    $query = "DELETE FROM pagos WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Pago eliminado';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Error al eliminar el pago';
        $_SESSION['message_type'] = 'danger';
    }
    
    header('Location: finanzas.php');
    exit();
}

// Obtener estadísticas financieras
$estadisticas = [];

// Total ingresos del período
$query = "SELECT SUM(monto) as total FROM pagos 
          WHERE estado = 'pagado' 
          AND fecha_pago BETWEEN :fecha_inicio AND :fecha_fin";
$stmt = $conn->prepare($query);
$stmt->bindParam(':fecha_inicio', $fecha_inicio);
$stmt->bindParam(':fecha_fin', $fecha_fin);
$stmt->execute();
$estadisticas['total_ingresos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Pendientes por cobrar
$query = "SELECT SUM(monto) as total FROM pagos 
          WHERE estado = 'pendiente' 
          AND fecha_vencimiento >= CURDATE()";
$stmt = $conn->prepare($query);
$stmt->execute();
$estadisticas['pendientes_cobrar'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Vencidos
$query = "SELECT SUM(monto) as total FROM pagos 
          WHERE estado = 'pendiente' 
          AND fecha_vencimiento < CURDATE()";
$stmt = $conn->prepare($query);
$stmt->execute();
$estadisticas['vencidos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Ingresos por concepto
$query = "SELECT concepto, SUM(monto) as total 
          FROM pagos 
          WHERE estado = 'pagado' 
          AND fecha_pago BETWEEN :fecha_inicio AND :fecha_fin 
          GROUP BY concepto 
          ORDER BY total DESC";
$stmt = $conn->prepare($query);
$stmt->bindParam(':fecha_inicio', $fecha_inicio);
$stmt->bindParam(':fecha_fin', $fecha_fin);
$stmt->execute();
$ingresos_concepto = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ingresos por mes (últimos 6 meses)
$query = "SELECT 
            DATE_FORMAT(fecha_pago, '%Y-%m') as mes,
            SUM(monto) as total
          FROM pagos 
          WHERE estado = 'pagado' 
          AND fecha_pago >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
          GROUP BY DATE_FORMAT(fecha_pago, '%Y-%m')
          ORDER BY mes";
$stmt = $conn->prepare($query);
$stmt->execute();
$ingresos_mensuales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener pagos
$query = "SELECT p.*, e.codigo_estudiante, e.nombres, e.apellidos 
          FROM pagos p 
          JOIN estudiantes e ON p.estudiante_id = e.id 
          WHERE 1=1";

$params = [];

if (!empty($estado)) {
    $query .= " AND p.estado = :estado";
    $params[':estado'] = $estado;
}

if (!empty($estudiante_id)) {
    $query .= " AND p.estudiante_id = :estudiante_id";
    $params[':estudiante_id'] = $estudiante_id;
}

if (!empty($tipo_concepto)) {
    $query .= " AND p.concepto LIKE :tipo_concepto";
    $params[':tipo_concepto'] = "%$tipo_concepto%";
}

$query .= " ORDER BY p.fecha_vencimiento DESC, p.estado";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estudiantes para filtro
$query = "SELECT id, codigo_estudiante, nombres, apellidos FROM estudiantes WHERE estado = 'activo' ORDER BY apellidos";
$stmt = $conn->query($query);
$estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Conceptos comunes
$conceptos = ['Matrícula', 'Mensualidad', 'Inscripción', 'Derecho de Grado', 'Certificados', 'Otros'];

include '../../includes/header.php';

// Mostrar mensajes
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-' . $_SESSION['message_type'] . ' alert-dismissible fade show" role="alert">
            ' . $_SESSION['message'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['message'], $_SESSION['message_type']);
}
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Gestión Financiera</h5>
        <div>
            <button type="button" class="btn btn-unexca" data-bs-toggle="modal" data-bs-target="#modalRegistrarPago">
                <i class="fas fa-plus me-2"></i>Registrar Pago
            </button>
            <a href="reportes.php?tipo=financiero" class="btn btn-outline-unexca ms-2">
                <i class="fas fa-chart-pie me-2"></i>Reportes
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <!-- Filtros -->
        <div class="row mb-4">
            <div class="col-md-12">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label for="fecha_inicio" class="form-label">Desde</label>
                        <input type="date" class="form-control" name="fecha_inicio" 
                               value="<?php echo $fecha_inicio; ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="fecha_fin" class="form-label">Hasta</label>
                        <input type="date" class="form-control" name="fecha_fin" 
                               value="<?php echo $fecha_fin; ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" name="estado">
                            <option value="">Todos</option>
                            <option value="pagado" <?php echo ($estado == 'pagado') ? 'selected' : ''; ?>>Pagado</option>
                            <option value="pendiente" <?php echo ($estado == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="vencido" <?php echo ($estado == 'vencido') ? 'selected' : ''; ?>>Vencido</option>
                            <option value="cancelado" <?php echo ($estado == 'cancelado') ? 'selected' : ''; ?>>Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="estudiante_id" class="form-label">Estudiante</label>
                        <select class="form-select" name="estudiante_id">
                            <option value="">Todos los estudiantes</option>
                            <?php foreach ($estudiantes as $est): ?>
                            <option value="<?php echo $est['id']; ?>" 
                                    <?php echo ($estudiante_id == $est['id']) ? 'selected' : ''; ?>>
                                <?php echo $est['codigo_estudiante'] . ' - ' . $est['nombres'] . ' ' . $est['apellidos']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="tipo_concepto" class="form-label">Concepto</label>
                        <select class="form-select" name="tipo_concepto">
                            <option value="">Todos</option>
                            <?php foreach ($conceptos as $concepto): ?>
                            <option value="<?php echo $concepto; ?>" 
                                    <?php echo ($tipo_concepto == $concepto) ? 'selected' : ''; ?>>
                                <?php echo $concepto; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12 mt-3">
                        <button type="submit" class="btn btn-unexca">
                            <i class="fas fa-filter me-2"></i>Filtrar
                        </button>
                        <a href="finanzas.php" class="btn btn-outline-secondary">
                            <i class="fas fa-redo me-2"></i>Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card border-left-primary border-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-primary">Ingresos del Período</h6>
                                <h3 class="mb-0">$<?php echo number_format($estadisticas['total_ingresos'], 2); ?></h3>
                            </div>
                            <div class="icon-circle bg-primary">
                                <i class="fas fa-dollar-sign text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card border-left-success border-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-success">Por Cobrar</h6>
                                <h3 class="mb-0">$<?php echo number_format($estadisticas['pendientes_cobrar'], 2); ?></h3>
                            </div>
                            <div class="icon-circle bg-success">
                                <i class="fas fa-clock text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card border-left-danger border-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-danger">Vencidos</h6>
                                <h3 class="mb-0">$<?php echo number_format($estadisticas['vencidos'], 2); ?></h3>
                            </div>
                            <div class="icon-circle bg-danger">
                                <i class="fas fa-exclamation-triangle text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card border-left-warning border-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-warning">Total Registros</h6>
                                <h3 class="mb-0"><?php echo count($pagos); ?></h3>
                            </div>
                            <div class="icon-circle bg-warning">
                                <i class="fas fa-file-invoice-dollar text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gráficos -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Ingresos por Concepto</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="chartIngresosConcepto" height="200"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Ingresos Mensuales (Últimos 6 meses)</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="chartIngresosMensuales" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Listado de pagos -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Registro de Pagos</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Fecha Venc.</th>
                                <th>Estudiante</th>
                                <th>Concepto</th>
                                <th>Monto</th>
                                <th>Método</th>
                                <th>Referencia</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pagos)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No hay pagos registrados</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($pagos as $pago): 
                                $estado_class = '';
                                $estado_text = '';
                                switch ($pago['estado']) {
                                    case 'pagado':
                                        $estado_class = 'success';
                                        $estado_text = 'Pagado';
                                        break;
                                    case 'pendiente':
                                        $vencimiento = new DateTime($pago['fecha_vencimiento']);
                                        $hoy = new DateTime();
                                        if ($vencimiento < $hoy) {
                                            $estado_class = 'danger';
                                            $estado_text = 'Vencido';
                                        } else {
                                            $estado_class = 'warning';
                                            $estado_text = 'Pendiente';
                                        }
                                        break;
                                    case 'vencido':
                                        $estado_class = 'danger';
                                        $estado_text = 'Vencido';
                                        break;
                                    case 'cancelado':
                                        $estado_class = 'secondary';
                                        $estado_text = 'Cancelado';
                                        break;
                                }
                            ?>
                            <tr>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($pago['fecha_vencimiento'])); ?>
                                    <?php if ($pago['fecha_pago']): ?>
                                    <br>
                                    <small class="text-muted">Pagado: <?php echo date('d/m/Y', strtotime($pago['fecha_pago'])); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo $pago['codigo_estudiante']; ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo $pago['nombres'] . ' ' . $pago['apellidos']; ?></small>
                                </td>
                                <td><?php echo $pago['concepto']; ?></td>
                                <td>
                                    <strong>$<?php echo number_format($pago['monto'], 2); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $pago['metodo_pago']; ?></span>
                                </td>
                                <td>
                                    <?php if ($pago['referencia']): ?>
                                    <code><?php echo $pago['referencia']; ?></code>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $estado_class; ?>">
                                        <?php echo $estado_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($pago['estado'] == 'pendiente'): ?>
                                        <a href="finanzas.php?action=marcar_pagado&id=<?php echo $pago['id']; ?>" 
                                           class="btn btn-outline-success" title="Marcar como pagado">
                                            <i class="fas fa-check"></i>
                                        </a>
                                        <?php endif; ?>
                                        <a href="finanzas.php?action=eliminar&id=<?php echo $pago['id']; ?>" 
                                           class="btn btn-outline-danger confirm-delete" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para registrar pago -->
<div class="modal fade" id="modalRegistrarPago" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Registrar Nuevo Pago</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formRegistrarPago" method="POST" action="">
                    <input type="hidden" name="registrar_pago" value="1">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="estudiante_id" class="form-label">Estudiante *</label>
                            <select class="form-select" id="estudiante_id" name="estudiante_id" required>
                                <option value="">Seleccionar estudiante</option>
                                <?php foreach ($estudiantes as $est): ?>
                                <option value="<?php echo $est['id']; ?>">
                                    <?php echo $est['codigo_estudiante'] . ' - ' . $est['nombres'] . ' ' . $est['apellidos']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="concepto" class="form-label">Concepto *</label>
                            <select class="form-select" id="concepto" name="concepto" required>
                                <option value="">Seleccionar concepto</option>
                                <?php foreach ($conceptos as $concepto): ?>
                                <option value="<?php echo $concepto; ?>"><?php echo $concepto; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="monto" class="form-label">Monto ($) *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="monto" name="monto" 
                                       step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="fecha_vencimiento" class="form-label">Fecha Vencimiento *</label>
                            <input type="date" class="form-control" id="fecha_vencimiento" name="fecha_vencimiento" 
                                   value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="metodo_pago" class="form-label">Método de Pago</label>
                            <select class="form-select" id="metodo_pago" name="metodo_pago">
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="cheque">Cheque</option>
                                <option value="deposito">Depósito</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="referencia" class="form-label">Referencia/Número</label>
                        <input type="text" class="form-control" id="referencia" name="referencia" 
                               placeholder="Número de transacción, cheque, etc.">
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Nota:</strong> El pago se registrará como pendiente. Se debe marcar como pagado cuando se reciba el pago.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" form="formRegistrarPago" class="btn btn-unexca">
                    <i class="fas fa-save me-2"></i>Registrar Pago
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts para gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de ingresos por concepto
    const ctxConcepto = document.getElementById('chartIngresosConcepto').getContext('2d');
    const labelsConcepto = <?php echo json_encode(array_column($ingresos_concepto, 'concepto')); ?>;
    const dataConcepto = <?php echo json_encode(array_column($ingresos_concepto, 'total')); ?>;
    
    new Chart(ctxConcepto, {
        type: 'doughnut',
        data: {
            labels: labelsConcepto,
            datasets: [{
                data: dataConcepto,
                backgroundColor: [
                    '#0056b3', '#28a745', '#dc3545', '#ffc107', '#17a2b8', '#6c757d'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    // Gráfico de ingresos mensuales
    const ctxMensual = document.getElementById('chartIngresosMensuales').getContext('2d');
    const labelsMensual = <?php echo json_encode(array_column($ingresos_mensuales, 'mes')); ?>;
    const dataMensual = <?php echo json_encode(array_column($ingresos_mensuales, 'total')); ?>;
    
    new Chart(ctxMensual, {
        type: 'bar',
        data: {
            labels: labelsMensual,
            datasets: [{
                label: 'Ingresos ($)',
                data: dataMensual,
                backgroundColor: '#0056b3',
                borderColor: '#003366',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Monto ($)'
                    }
                }
            }
        }
    });
    
    // Auto-completar fecha de vencimiento según concepto
    $('#concepto').change(function() {
        const concepto = $(this).val();
        let fechaVencimiento = new Date();
        
        switch(concepto) {
            case 'Matrícula':
                // Vence en 15 días
                fechaVencimiento.setDate(fechaVencimiento.getDate() + 15);
                break;
            case 'Mensualidad':
                // Vence a fin de mes
                fechaVencimiento = new Date(fechaVencimiento.getFullYear(), fechaVencimiento.getMonth() + 1, 0);
                break;
            case 'Inscripción':
                // Vence en 7 días
                fechaVencimiento.setDate(fechaVencimiento.getDate() + 7);
                break;
            default:
                // Vence en 30 días por defecto
                fechaVencimiento.setDate(fechaVencimiento.getDate() + 30);
        }
        
        $('#fecha_vencimiento').val(fechaVencimiento.toISOString().split('T')[0]);
    });
    
    // Auto-completar monto según concepto
    $('#concepto').change(function() {
        const concepto = $(this).val();
        let monto = 0;
        
        switch(concepto) {
            case 'Matrícula':
                monto = 500.00;
                break;
            case 'Mensualidad':
                monto = 300.00;
                break;
            case 'Inscripción':
                monto = 100.00;
                break;
            case 'Derecho de Grado':
                monto = 200.00;
                break;
            case 'Certificados':
                monto = 50.00;
                break;
            default:
                monto = 0;
        }
        
        if (monto > 0) {
            $('#monto').val(monto);
        }
    });
});
</script>

<style>
.icon-circle {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.border-left-primary { border-left-color: #0056b3 !important; }
.border-left-success { border-left-color: #28a745 !important; }
.border-left-danger { border-left-color: #dc3545 !important; }
.border-left-warning { border-left-color: #ffc107 !important; }
</style>

<?php include '../../includes/footer.php'; ?>