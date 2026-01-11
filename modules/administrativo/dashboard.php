<?php
// modules/administrativo/dashboard.php
require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SESSION['rol'] != 'administrador') {
    header('Location: ../../index.php');
    exit();
}

$page_title = 'Dashboard Administrativo';

$db = new Database();
$conn = $db->getConnection();

// Estadísticas detalladas
$estadisticas = [];

// Total estudiantes activos
$query = "SELECT COUNT(*) as total FROM estudiantes WHERE estado = 'activo'";
$stmt = $conn->query($query);
$estadisticas['estudiantes_activos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Estudiantes por género
$query = "SELECT genero, COUNT(*) as total FROM estudiantes WHERE estado = 'activo' GROUP BY genero";
$stmt = $conn->query($query);
$estadisticas['estudiantes_genero'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total docentes activos
$query = "SELECT COUNT(*) as total FROM docentes WHERE estado = 'activo'";
$stmt = $conn->query($query);
$estadisticas['docentes_activos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Cursos activos
$query = "SELECT COUNT(*) as total FROM cursos WHERE estado = 'activo'";
$stmt = $conn->query($query);
$estadisticas['cursos_activos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Secciones abiertas
$query = "SELECT COUNT(*) as total FROM secciones WHERE estado IN ('abierta', 'en_progreso')";
$stmt = $conn->query($query);
$estadisticas['secciones_activas'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pagos pendientes
$query = "SELECT SUM(monto) as total FROM pagos WHERE estado = 'pendiente'";
$stmt = $conn->query($query);
$estadisticas['pagos_pendientes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Ingresos del mes
$query = "SELECT SUM(monto) as total FROM pagos WHERE estado = 'pagado' 
          AND MONTH(fecha_pago) = MONTH(CURRENT_DATE()) 
          AND YEAR(fecha_pago) = YEAR(CURRENT_DATE())";
$stmt = $conn->query($query);
$estadisticas['ingresos_mes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Nuevos estudiantes este mes
$query = "SELECT COUNT(*) as total FROM estudiantes 
          WHERE MONTH(fecha_ingreso) = MONTH(CURRENT_DATE()) 
          AND YEAR(fecha_ingreso) = YEAR(CURRENT_DATE())";
$stmt = $conn->query($query);
$estadisticas['nuevos_estudiantes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Últimos 5 pagos recibidos
$query = "SELECT p.*, e.codigo_estudiante, e.nombres, e.apellidos 
          FROM pagos p 
          JOIN estudiantes e ON p.estudiante_id = e.id 
          WHERE p.estado = 'pagado' 
          ORDER BY p.fecha_pago DESC 
          LIMIT 5";
$stmt = $conn->query($query);
$ultimos_pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Solicitudes pendientes
$query = "SELECT COUNT(*) as total FROM solicitudes WHERE estado = 'pendiente'";
$stmt = $conn->query($query);
$estadisticas['solicitudes_pendientes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Matrículas por período académico
$query = "SELECT s.periodo_academico, COUNT(DISTINCT m.estudiante_id) as total 
          FROM matriculas m 
          JOIN secciones s ON m.seccion_id = s.id 
          GROUP BY s.periodo_academico 
          ORDER BY s.periodo_academico DESC 
          LIMIT 5";
$stmt = $conn->query($query);
$matriculas_periodo = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Alertas del sistema
$alertas = [];

// Secciones sin docente asignado
$query = "SELECT COUNT(*) as total FROM secciones WHERE docente_id IS NULL AND estado = 'abierta'";
$stmt = $conn->query($query);
$sin_docente = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
if ($sin_docente > 0) {
    $alertas[] = [
        'tipo' => 'warning',
        'mensaje' => "$sin_docente sección(es) sin docente asignado",
        'url' => 'secciones.php?filtro=sin_docente'
    ];
}

// Pagos vencidos
$query = "SELECT COUNT(*) as total FROM pagos 
          WHERE estado = 'pendiente' 
          AND fecha_vencimiento < CURDATE()";
$stmt = $conn->query($query);
$pagos_vencidos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
if ($pagos_vencidos > 0) {
    $alertas[] = [
        'tipo' => 'danger',
        'mensaje' => "$pagos_vencidos pago(s) vencido(s)",
        'url' => 'pagos.php?estado=vencido'
    ];
}

// Cursos sin secciones
$query = "SELECT COUNT(*) as total FROM cursos c 
          WHERE c.estado = 'activo' 
          AND NOT EXISTS (SELECT 1 FROM secciones s WHERE s.curso_id = c.id AND s.estado IN ('abierta', 'en_progreso'))";
$stmt = $conn->query($query);
$cursos_sin_secciones = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
if ($cursos_sin_secciones > 0) {
    $alertas[] = [
        'tipo' => 'info',
        'mensaje' => "$cursos_sin_secciones curso(s) sin secciones activas",
        'url' => 'cursos.php?filtro=sin_secciones'
    ];
}

include '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3 class="mb-2">Panel de Control Administrativo</h3>
                        <p class="text-muted mb-0">Bienvenido, <?php echo $_SESSION['username']; ?></p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="btn-group">
                            <button type="button" class="btn btn-unexca" data-bs-toggle="modal" data-bs-target="#modalAccionesRapidas">
                                <i class="fas fa-bolt me-2"></i>Acciones Rápidas
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Alertas del sistema -->
<?php if (!empty($alertas)): ?>
<div class="row mb-4">
    <div class="col-md-12">
        <?php foreach ($alertas as $alerta): ?>
        <div class="alert alert-<?php echo $alerta['tipo']; ?> alert-dismissible fade show mb-2" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $alerta['mensaje']; ?>
            <a href="<?php echo $alerta['url']; ?>" class="alert-link ms-2">Ver detalles</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <!-- Widgets principales -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary border-3">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Estudiantes Activos
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($estadisticas['estudiantes_activos']); ?>
                        </div>
                        <div class="mt-2">
                            <small class="text-success">
                                <i class="fas fa-arrow-up me-1"></i>
                                <?php echo number_format($estadisticas['nuevos_estudiantes']); ?> nuevos
                            </small>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="estudiantes.php" class="small">Ver detalles <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success border-3">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Ingresos del Mes
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            $<?php echo number_format($estadisticas['ingresos_mes'], 2); ?>
                        </div>
                        <div class="mt-2">
                            <small class="text-danger">
                                <i class="fas fa-exclamation-circle me-1"></i>
                                $<?php echo number_format($estadisticas['pagos_pendientes'], 2); ?> pendientes
                            </small>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-success"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="finanzas.php" class="small">Reporte financiero <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info border-3">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Actividad Académica
                        </div>
                        <div class="row no-gutters align-items-center">
                            <div class="col-auto">
                                <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800">
                                    <?php echo $estadisticas['secciones_activas']; ?>
                                </div>
                            </div>
                            <div class="col">
                                <div class="progress progress-sm mr-2">
                                    <div class="progress-bar bg-info" role="progressbar" 
                                         style="width: <?php echo min(100, ($estadisticas['secciones_activas'] / 50) * 100); ?>%">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                <?php echo $estadisticas['docentes_activos']; ?> docentes | <?php echo $estadisticas['cursos_activos']; ?> cursos
                            </small>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chalkboard-teacher fa-2x text-info"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="secciones.php" class="small">Gestionar secciones <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning border-3">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Solicitudes Pendientes
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $estadisticas['solicitudes_pendientes']; ?>
                        </div>
                        <div class="mt-2">
                            <small class="text-warning">
                                <i class="fas fa-clock me-1"></i>
                                Requieren atención
                            </small>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clipboard-list fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="solicitudes.php" class="small">Revisar solicitudes <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Gráfico de distribución de estudiantes -->
    <div class="col-xl-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Distribución Académica</h6>
                <div class="dropdown no-arrow" style="position: absolute; right: 1rem; top: 1rem;">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                            data-bs-toggle="dropdown">
                        <i class="fas fa-filter"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="#" onclick="cambiarVista('carrera')">Por Carrera</a>
                        <a class="dropdown-item" href="#" onclick="cambiarVista('semestre')">Por Semestre</a>
                        <a class="dropdown-item" href="#" onclick="cambiarVista('genero')">Por Género</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-area">
                    <canvas id="distribucionChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Últimos pagos -->
    <div class="col-xl-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Últimos Pagos Recibidos</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if (empty($ultimos_pagos)): ?>
                    <div class="list-group-item text-center py-4">
                        <i class="fas fa-money-bill-wave fa-2x text-muted mb-3"></i>
                        <p class="text-muted mb-0">No hay pagos recientes</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($ultimos_pagos as $pago): ?>
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><?php echo $pago['concepto']; ?></h6>
                                <small class="text-muted">
                                    <?php echo $pago['nombres'] . ' ' . $pago['apellidos']; ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success">$<?php echo number_format($pago['monto'], 2); ?></span>
                                <small class="d-block text-muted">
                                    <?php echo date('d/m/Y', strtotime($pago['fecha_pago'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="pagos.php" class="small">Ver todos los pagos</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Actividad reciente -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Actividad Reciente del Sistema</h6>
            </div>
            <div class="card-body">
                <div class="activity-timeline">
                    <?php
                    // Actividad simulada - en producción esto vendría de una tabla de logs
                    $actividades = [
                        ['hora' => '10:30', 'usuario' => 'Admin', 'accion' => 'Registró nuevo estudiante', 'icono' => 'user-plus', 'color' => 'success'],
                        ['hora' => '09:45', 'usuario' => 'Prof. García', 'accion' => 'Cargó calificaciones', 'icono' => 'edit', 'color' => 'primary'],
                        ['hora' => '09:15', 'usuario' => 'Est. Rodríguez', 'accion' => 'Realizó pago de matrícula', 'icono' => 'dollar-sign', 'color' => 'success'],
                        ['hora' => 'Ayer', 'usuario' => 'Admin', 'accion' => 'Creó nueva sección', 'icono' => 'layer-group', 'color' => 'info'],
                        ['hora' => 'Ayer', 'usuario' => 'Sistema', 'accion' => 'Backup automático completado', 'icono' => 'database', 'color' => 'secondary'],
                    ];
                    
                    foreach ($actividades as $actividad):
                    ?>
                    <div class="activity-item d-flex">
                        <div class="activity-icon bg-<?php echo $actividad['color']; ?> text-white rounded-circle">
                            <i class="fas fa-<?php echo $actividad['icono']; ?>"></i>
                        </div>
                        <div class="activity-content ms-3">
                            <h6 class="mb-1"><?php echo $actividad['accion']; ?></h6>
                            <div class="small text-muted">
                                <?php echo $actividad['hora']; ?> por <?php echo $actividad['usuario']; ?>
                            </div>
                        </div>
                    </div>
                    <div class="vertical-line"></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Métricas rápidas -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Métricas del Sistema</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="metric-card">
                            <div class="metric-icon bg-primary">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-value"><?php echo number_format($estadisticas['estudiantes_activos']); ?></div>
                                <div class="metric-label">Estudiantes Activos</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="metric-card">
                            <div class="metric-icon bg-success">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-value"><?php echo $estadisticas['docentes_activos']; ?></div>
                                <div class="metric-label">Docentes Activos</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="metric-card">
                            <div class="metric-icon bg-info">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-value"><?php echo $estadisticas['cursos_activos']; ?></div>
                                <div class="metric-label">Cursos Activos</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="metric-card">
                            <div class="metric-icon bg-warning">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-value"><?php echo $estadisticas['secciones_activas']; ?></div>
                                <div class="metric-label">Secciones Activas</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Progreso de objetivos -->
                <div class="mt-4">
                    <h6 class="mb-3">Progreso de Objetivos Mensuales</h6>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Matrículas nuevas</span>
                            <span>85%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 85%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Recaudación</span>
                            <span>72%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: 72%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Satisfacción estudiantil</span>
                            <span>90%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: 90%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de acciones rápidas -->
<div class="modal fade" id="modalAccionesRapidas" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Acciones Rápidas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <a href="estudiantes.php?action=nuevo" class="card quick-action-card text-center">
                            <div class="card-body">
                                <i class="fas fa-user-plus fa-2x text-primary mb-3"></i>
                                <h6>Nuevo Estudiante</h6>
                                <small class="text-muted">Registrar nuevo estudiante</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="docentes.php?action=nuevo" class="card quick-action-card text-center">
                            <div class="card-body">
                                <i class="fas fa-chalkboard-teacher fa-2x text-success mb-3"></i>
                                <h6>Nuevo Docente</h6>
                                <small class="text-muted">Contratar nuevo docente</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="cursos.php?action=nuevo" class="card quick-action-card text-center">
                            <div class="card-body">
                                <i class="fas fa-book fa-2x text-info mb-3"></i>
                                <h6>Nuevo Curso</h6>
                                <small class="text-muted">Crear nuevo curso</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="secciones.php?action=nuevo" class="card quick-action-card text-center">
                            <div class="card-body">
                                <i class="fas fa-layer-group fa-2x text-warning mb-3"></i>
                                <h6>Nueva Sección</h6>
                                <small class="text-muted">Abrir nueva sección</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="pagos.php?action=nuevo" class="card quick-action-card text-center">
                            <div class="card-body">
                                <i class="fas fa-money-bill-wave fa-2x text-danger mb-3"></i>
                                <h6>Registrar Pago</h6>
                                <small class="text-muted">Registrar pago manual</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="reportes.php?tipo=financiero" class="card quick-action-card text-center">
                            <div class="card-body">
                                <i class="fas fa-chart-pie fa-2x text-secondary mb-3"></i>
                                <h6>Reporte Financiero</h6>
                                <small class="text-muted">Generar reporte</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts para gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de distribución
    const ctx = document.getElementById('distribucionChart').getContext('2d');
    let distribucionChart;
    
    function inicializarGrafico(tipo = 'carrera') {
        if (distribucionChart) {
            distribucionChart.destroy();
        }
        
        let labels, data, backgroundColor;
        
        switch(tipo) {
            case 'carrera':
                labels = ['Ingeniería', 'Administración', 'Derecho', 'Medicina', 'Arquitectura'];
                data = [450, 320, 280, 190, 150];
                backgroundColor = ['#0056b3', '#28a745', '#dc3545', '#17a2b8', '#ffc107'];
                break;
            case 'semestre':
                labels = ['1°', '2°', '3°', '4°', '5°', '6°', '7°', '8°', '9°', '10°'];
                data = [120, 115, 110, 105, 100, 95, 90, 85, 80, 75];
                backgroundColor = Array(10).fill().map((_, i) => `hsl(${i * 36}, 70%, 60%)`);
                break;
            case 'genero':
                labels = ['Masculino', 'Femenino', 'Otro'];
                data = [850, 650, 50];
                backgroundColor = ['#0056b3', '#dc3545', '#28a745'];
                break;
        }
        
        distribucionChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Cantidad',
                    data: data,
                    backgroundColor: backgroundColor,
                    borderColor: backgroundColor.map(color => color.replace('0.8', '1')),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: `Distribución por ${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Cantidad de Estudiantes'
                        }
                    }
                }
            }
        });
    }
    
    // Inicializar con vista por carrera
    inicializarGrafico('carrera');
    
    window.cambiarVista = function(tipo) {
        inicializarGrafico(tipo);
    };
    
    // Auto-refresh cada 5 minutos para datos en tiempo real
    setInterval(() => {
        // Aquí podrías hacer una llamada AJAX para actualizar datos
        console.log('Actualizando datos del dashboard...');
    }, 300000);
});
</script>

<style>
.quick-action-card {
    transition: all 0.3s ease;
    border: 1px solid #dee2e6;
    text-decoration: none;
    color: inherit;
}

.quick-action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    border-color: #0056b3;
}

.activity-item {
    padding: 15px 0;
    position: relative;
}

.activity-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
}

.vertical-line {
    position: absolute;
    left: 20px;
    top: 55px;
    bottom: -15px;
    width: 2px;
    background-color: #e9ecef;
    z-index: 1;
}

.activity-item:last-child .vertical-line {
    display: none;
}

.metric-card {
    display: flex;
    align-items: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    border-left: 4px solid #0056b3;
}

.metric-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    flex-shrink: 0;
}

.metric-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: #343a40;
}

.metric-label {
    font-size: 0.875rem;
    color: #6c757d;
}

.chart-area {
    position: relative;
    height: 300px;
    width: 100%;
}

.border-left-primary { border-left-color: #0056b3 !important; }
.border-left-success { border-left-color: #28a745 !important; }
.border-left-info { border-left-color: #17a2b8 !important; }
.border-left-warning { border-left-color: #ffc107 !important; }
</style>

<?php include '../../includes/footer.php'; ?>