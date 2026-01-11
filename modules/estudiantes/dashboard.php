<?php
// modules/estudiantes/dashboard.php
require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SESSION['rol'] != 'estudiante') {
    header('Location: ../../index.php');
    exit();
}

$page_title = 'Dashboard Estudiante';

$db = new Database();
$conn = $db->getConnection();

// Obtener información del estudiante
$query = "SELECT e.*, c.nombre as carrera, u.email 
          FROM estudiantes e 
          LEFT JOIN carreras c ON e.carrera_id = c.id 
          LEFT JOIN usuarios u ON e.usuario_id = u.id 
          WHERE u.id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener cursos actuales
$query = "SELECT s.codigo_seccion, cu.nombre, cu.creditos, d.nombres as docente_nombre, 
                 s.horario, s.aula, m.estado
          FROM matriculas m 
          JOIN secciones s ON m.seccion_id = s.id 
          JOIN cursos cu ON s.curso_id = cu.id 
          JOIN docentes d ON s.docente_id = d.id 
          WHERE m.estudiante_id = :estudiante_id 
          AND m.estado = 'matriculado'
          AND s.estado = 'en_progreso'";
$stmt = $conn->prepare($query);
$stmt->bindParam(':estudiante_id', $estudiante['id']);
$stmt->execute();
$cursos_actuales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener últimas calificaciones
$query = "SELECT cu.nombre as curso, te.nombre as evaluacion, ca.nota, ca.fecha_registro
          FROM calificaciones ca 
          JOIN tipos_evaluacion te ON ca.tipo_evaluacion_id = te.id 
          JOIN matriculas m ON ca.matricula_id = m.id 
          JOIN secciones s ON m.seccion_id = s.id 
          JOIN cursos cu ON s.curso_id = cu.id 
          WHERE m.estudiante_id = :estudiante_id 
          ORDER BY ca.fecha_registro DESC 
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bindParam(':estudiante_id', $estudiante['id']);
$stmt->execute();
$ultimas_calificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener próximos pagos
$query = "SELECT concepto, monto, fecha_vencimiento 
          FROM pagos 
          WHERE estudiante_id = :estudiante_id 
          AND estado = 'pendiente' 
          AND fecha_vencimiento >= CURDATE() 
          ORDER BY fecha_vencimiento 
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bindParam(':estudiante_id', $estudiante['id']);
$stmt->execute();
$proximos_pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                        <div class="avatar-lg mx-auto">
                            <div class="avatar-title bg-light rounded-circle" style="width: 100px; height: 100px;">
                                <i class="fas fa-user-graduate fa-3x text-primary"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h3 class="mb-1"><?php echo $estudiante['nombres'] . ' ' . $estudiante['apellidos']; ?></h3>
                        <p class="text-muted mb-2"><?php echo $estudiante['codigo_estudiante']; ?></p>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><i class="fas fa-graduation-cap me-2"></i><?php echo $estudiante['carrera']; ?></p>
                                <p class="mb-1"><i class="fas fa-layer-group me-2"></i>Semestre: <?php echo $estudiante['semestre_actual']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><i class="fas fa-star me-2"></i>Promedio: <?php echo number_format($estudiante['promedio_general'], 2); ?></p>
                                <p class="mb-1"><i class="fas fa-check-circle me-2"></i>Créditos: <?php echo $estudiante['creditos_aprobados']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="btn-group">
                            <a href="perfil.php" class="btn btn-unexca">
                                <i class="fas fa-user me-2"></i>Mi Perfil
                            </a>
                            <a href="notas.php" class="btn btn-outline-unexca">
                                <i class="fas fa-graduation-cap me-2"></i>Ver Notas
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Cursos actuales -->
    <div class="col-xl-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Mis Cursos Actuales</h5>
                <a href="horario.php" class="btn btn-sm btn-unexca">Ver Horario</a>
            </div>
            <div class="card-body">
                <?php if (empty($cursos_actuales)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No tienes cursos matriculados este período</p>
                    <a href="matricula.php" class="btn btn-unexca">Matricular Cursos</a>
                </div>
                <?php else: ?>
                <div class="list-group">
                    <?php foreach ($cursos_actuales as $curso): ?>
                    <div class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><?php echo $curso['nombre']; ?></h6>
                                <small class="text-muted">
                                    <i class="fas fa-chalkboard-teacher me-1"></i><?php echo $curso['docente_nombre']; ?>
                                </small>
                            </div>
                            <span class="badge bg-unexca"><?php echo $curso['creditos']; ?> créditos</span>
                        </div>
                        <div class="mt-2">
                            <small>
                                <i class="fas fa-clock me-1"></i><?php echo $curso['horario']; ?>
                                <i class="fas fa-door-open ms-3 me-1"></i><?php echo $curso['aula']; ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Próximos pagos -->
    <div class="col-xl-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Próximos Pagos</h5>
            </div>
            <div class="card-body">
                <?php if (empty($proximos_pagos)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <p class="text-success">No tienes pagos pendientes</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Concepto</th>
                                <th>Monto</th>
                                <th>Vencimiento</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($proximos_pagos as $pago): 
                                $hoy = new DateTime();
                                $vencimiento = new DateTime($pago['fecha_vencimiento']);
                                $dias_restantes = $hoy->diff($vencimiento)->days;
                                $clase = ($dias_restantes <= 3) ? 'text-danger' : (($dias_restantes <= 7) ? 'text-warning' : 'text-success');
                            ?>
                            <tr>
                                <td><?php echo $pago['concepto']; ?></td>
                                <td><strong><?php echo number_format($pago['monto'], 2); ?> $</strong></td>
                                <td class="<?php echo $clase; ?>">
                                    <?php echo date('d/m/Y', strtotime($pago['fecha_vencimiento'])); ?>
                                    <small class="d-block">(<?php echo $dias_restantes; ?> días)</small>
                                </td>
                                <td>
                                    <a href="pagos.php" class="btn btn-sm btn-unexca">Pagar</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Últimas calificaciones -->
    <div class="col-xl-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Últimas Calificaciones</h5>
                <a href="notas.php" class="btn btn-sm btn-unexca">Ver Todas</a>
            </div>
            <div class="card-body">
                <?php if (empty($ultimas_calificaciones)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No hay calificaciones registradas</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Curso</th>
                                <th>Evaluación</th>
                                <th>Nota</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimas_calificaciones as $calif): 
                                $estado_clase = ($calif['nota'] >= 10) ? 'success' : 'danger';
                                $estado_texto = ($calif['nota'] >= 10) ? 'Aprobado' : 'Reprobado';
                            ?>
                            <tr>
                                <td><?php echo $calif['curso']; ?></td>
                                <td><?php echo $calif['evaluacion']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $estado_clase; ?>">
                                        <?php echo number_format($calif['nota'], 2); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($calif['fecha_registro'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $estado_clase; ?>">
                                        <?php echo $estado_texto; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Progreso académico -->
    <div class="col-xl-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Progreso Académico</h5>
            </div>
            <div class="card-body text-center">
                <div class="mb-4">
                    <div class="position-relative d-inline-block">
                        <canvas id="progressChart" width="150" height="150"></canvas>
                        <div class="position-absolute top-50 start-50 translate-middle">
                            <h2 class="mb-0"><?php echo number_format($estudiante['promedio_general'], 1); ?></h2>
                            <small class="text-muted">Promedio</small>
                        </div>
                    </div>
                </div>
                <div class="row text-center">
                    <div class="col-6">
                        <h4 class="text-primary"><?php echo $estudiante['creditos_aprobados']; ?></h4>
                        <small class="text-muted">Créditos Aprobados</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-success">
                            <?php 
                            $total_creditos = 180; // Esto debería venir de la carrera
                            echo $total_creditos - $estudiante['creditos_aprobados'];
                            ?>
                        </h4>
                        <small class="text-muted">Créditos Restantes</small>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar" role="progressbar" 
                             style="width: <?php echo min(100, ($estudiante['creditos_aprobados'] / 180) * 100); ?>%">
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block">
                        <?php echo round(($estudiante['creditos_aprobados'] / 180) * 100, 1); ?>% de la carrera completada
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Gráfico de progreso
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('progressChart').getContext('2d');
    const promedio = <?php echo $estudiante['promedio_general']; ?>;
    const porcentaje = Math.min(100, (promedio / 20) * 100);
    
    const progressChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [porcentaje, 100 - porcentaje],
                backgroundColor: ['#0056b3', '#e9ecef'],
                borderWidth: 0
            }]
        },
        options: {
            cutout: '70%',
            responsive: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    enabled: false
                }
            }
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>