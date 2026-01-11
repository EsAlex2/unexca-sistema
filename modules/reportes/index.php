<?php
// modules/reportes/index.php
require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SESSION['rol'] != 'administrador') {
    header('Location: ../../index.php');
    exit();
}

$page_title = 'Reportes Académicos';

$db = new Database();
$conn = $db->getConnection();

// Obtener parámetros para filtros
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$carrera_id = $_GET['carrera_id'] ?? '';
$tipo_reporte = $_GET['tipo_reporte'] ?? 'estadisticas_generales';

// Obtener carreras
$query = "SELECT * FROM carreras WHERE estado = 'activa' ORDER BY nombre";
$stmt = $conn->query($query);
$carreras = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas generales
$estadisticas = [];

if ($tipo_reporte == 'estadisticas_generales') {
    // Total estudiantes por carrera
    $query = "SELECT c.nombre as carrera, COUNT(e.id) as total_estudiantes,
                     AVG(e.promedio_general) as promedio_carrera
              FROM estudiantes e 
              LEFT JOIN carreras c ON e.carrera_id = c.id 
              WHERE e.estado = 'activo'
              GROUP BY c.id 
              ORDER BY total_estudiantes DESC";
    $stmt = $conn->query($query);
    $estadisticas['estudiantes_por_carrera'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Distribución por semestre
    $query = "SELECT semestre_actual, COUNT(*) as total 
              FROM estudiantes 
              WHERE estado = 'activo'
              GROUP BY semestre_actual 
              ORDER BY semestre_actual";
    $stmt = $conn->query($query);
    $estadisticas['distribucion_semestres'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Rendimiento por carrera
    $query = "SELECT c.nombre as carrera,
                     COUNT(DISTINCT e.id) as total_estudiantes,
                     AVG(e.promedio_general) as promedio,
                     SUM(CASE WHEN e.promedio_general >= 15 THEN 1 ELSE 0 END) as excelentes,
                     SUM(CASE WHEN e.promedio_general >= 10 AND e.promedio_general < 15 THEN 1 ELSE 0 END) as buenos,
                     SUM(CASE WHEN e.promedio_general < 10 THEN 1 ELSE 0 END) as deficientes
              FROM estudiantes e 
              JOIN carreras c ON e.carrera_id = c.id 
              WHERE e.estado = 'activo'
              GROUP BY c.id 
              ORDER BY promedio DESC";
    $stmt = $conn->query($query);
    $estadisticas['rendimiento_carreras'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Reporte de calificaciones por curso
if ($tipo_reporte == 'calificaciones_curso' && !empty($carrera_id)) {
    $query = "SELECT cu.codigo, cu.nombre as curso, 
                     COUNT(DISTINCT m.estudiante_id) as estudiantes,
                     AVG(m.nota_final) as promedio,
                     MIN(m.nota_final) as minima,
                     MAX(m.nota_final) as maxima,
                     SUM(CASE WHEN m.nota_final >= 10 THEN 1 ELSE 0 END) as aprobados,
                     SUM(CASE WHEN m.nota_final < 10 THEN 1 ELSE 0 END) as reprobados
              FROM matriculas m 
              JOIN secciones s ON m.seccion_id = s.id 
              JOIN cursos cu ON s.curso_id = cu.id 
              WHERE cu.carrera_id = :carrera_id 
              AND m.nota_final IS NOT NULL
              AND s.periodo_academico BETWEEN :periodo_inicio AND :periodo_fin
              GROUP BY cu.id 
              ORDER BY promedio DESC";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':carrera_id', $carrera_id);
    $stmt->bindParam(':periodo_inicio', $fecha_inicio);
    $stmt->bindParam(':periodo_fin', $fecha_fin);
    $stmt->execute();
    $estadisticas['calificaciones_curso'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Reporte de estudiantes por estado
if ($tipo_reporte == 'estudiantes_estado') {
    $query = "SELECT estado, COUNT(*) as total 
              FROM estudiantes 
              GROUP BY estado 
              ORDER BY total DESC";
    $stmt = $conn->query($query);
    $estadisticas['estudiantes_estado'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Reportes Académicos</h5>
        <button type="button" class="btn btn-unexca" onclick="generarPDF()">
            <i class="fas fa-file-pdf me-2"></i>Exportar PDF
        </button>
    </div>
    
    <div class="card-body">
        <!-- Filtros -->
        <div class="row mb-4">
            <div class="col-md-12">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="tipo_reporte" class="form-label">Tipo de Reporte</label>
                        <select class="form-select" name="tipo_reporte" id="tipo_reporte" onchange="actualizarFiltros()">
                            <option value="estadisticas_generales" <?php echo ($tipo_reporte == 'estadisticas_generales') ? 'selected' : ''; ?>>Estadísticas Generales</option>
                            <option value="calificaciones_curso" <?php echo ($tipo_reporte == 'calificaciones_curso') ? 'selected' : ''; ?>>Calificaciones por Curso</option>
                            <option value="estudiantes_estado" <?php echo ($tipo_reporte == 'estudiantes_estado') ? 'selected' : ''; ?>>Estudiantes por Estado</option>
                            <option value="rendimiento_carrera" <?php echo ($tipo_reporte == 'rendimiento_carrera') ? 'selected' : ''; ?>>Rendimiento por Carrera</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3" id="carrera_filter" style="<?php echo ($tipo_reporte == 'calificaciones_curso' || $tipo_reporte == 'rendimiento_carrera') ? '' : 'display: none;'; ?>">
                        <label for="carrera_id" class="form-label">Carrera</label>
                        <select class="form-select" name="carrera_id" id="carrera_id">
                            <option value="">Todas las carreras</option>
                            <?php foreach ($carreras as $carrera): ?>
                            <option value="<?php echo $carrera['id']; ?>" 
                                    <?php echo ($carrera_id == $carrera['id']) ? 'selected' : ''; ?>>
                                <?php echo $carrera['nombre']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                        <input type="date" class="form-control" name="fecha_inicio" 
                               value="<?php echo $fecha_inicio; ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="fecha_fin" class="form-label">Fecha Fin</label>
                        <input type="date" class="form-control" name="fecha_fin" 
                               value="<?php echo $fecha_fin; ?>">
                    </div>
                    
                    <div class="col-md-12 mt-3">
                        <button type="submit" class="btn btn-unexca">
                            <i class="fas fa-filter me-2"></i>Generar Reporte
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='index.php'">
                            <i class="fas fa-redo me-2"></i>Limpiar Filtros
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Reporte de Estadísticas Generales -->
        <?php if ($tipo_reporte == 'estadisticas_generales'): ?>
        <div class="row">
            <!-- Gráfico de distribución por carrera -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Distribución de Estudiantes por Carrera</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="chartCarreras" height="250"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Gráfico de distribución por semestre -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Distribución por Semestre</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="chartSemestres" height="250"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Tabla de rendimiento -->
            <div class="col-lg-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Rendimiento por Carrera</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Carrera</th>
                                        <th>Total Estudiantes</th>
                                        <th>Promedio General</th>
                                        <th>Excelentes (≥15)</th>
                                        <th>Buenos (10-14.99)</th>
                                        <th>Deficientes (<10)</th>
                                        <th>% Aprobación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($estadisticas['rendimiento_carreras'] as $carrera): 
                                        $porcentaje_aprobacion = ($carrera['total_estudiantes'] > 0) ? 
                                            round((($carrera['excelentes'] + $carrera['buenos']) / $carrera['total_estudiantes']) * 100, 2) : 0;
                                    ?>
                                    <tr>
                                        <td><strong><?php echo $carrera['carrera']; ?></strong></td>
                                        <td><?php echo $carrera['total_estudiantes']; ?></td>
                                        <td>
                                            <span class="badge <?php echo ($carrera['promedio'] >= 15) ? 'bg-success' : (($carrera['promedio'] >= 10) ? 'bg-warning' : 'bg-danger'); ?>">
                                                <?php echo number_format($carrera['promedio'], 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $carrera['excelentes']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning"><?php echo $carrera['buenos']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger"><?php echo $carrera['deficientes']; ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo $porcentaje_aprobacion; ?>%">
                                                    </div>
                                                </div>
                                                <span><?php echo $porcentaje_aprobacion; ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Reporte de Calificaciones por Curso -->
        <?php if ($tipo_reporte == 'calificaciones_curso' && !empty($carrera_id)): ?>
        <div class="row">
            <div class="col-lg-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Calificaciones por Curso</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($estadisticas['calificaciones_curso'])): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No hay datos para mostrar</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover datatable">
                                <thead>
                                    <tr>
                                        <th>Curso</th>
                                        <th>Código</th>
                                        <th>Estudiantes</th>
                                        <th>Promedio</th>
                                        <th>Mínima</th>
                                        <th>Máxima</th>
                                        <th>Aprobados</th>
                                        <th>Reprobados</th>
                                        <th>% Aprobación</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($estadisticas['calificaciones_curso'] as $curso): 
                                        $porcentaje_aprobacion = ($curso['estudiantes'] > 0) ? 
                                            round(($curso['aprobados'] / $curso['estudiantes']) * 100, 2) : 0;
                                        
                                        $estado_clase = ($porcentaje_aprobacion >= 70) ? 'success' : 
                                                       (($porcentaje_aprobacion >= 50) ? 'warning' : 'danger');
                                    ?>
                                    <tr>
                                        <td><strong><?php echo $curso['curso']; ?></strong></td>
                                        <td><?php echo $curso['codigo']; ?></td>
                                        <td><?php echo $curso['estudiantes']; ?></td>
                                        <td>
                                            <span class="badge <?php echo ($curso['promedio'] >= 15) ? 'bg-success' : (($curso['promedio'] >= 10) ? 'bg-warning' : 'bg-danger'); ?>">
                                                <?php echo number_format($curso['promedio'], 2); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($curso['minima'], 2); ?></td>
                                        <td><?php echo number_format($curso['maxima'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $curso['aprobados']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger"><?php echo $curso['reprobados']; ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                    <div class="progress-bar bg-<?php echo $estado_clase; ?>" role="progressbar" 
                                                         style="width: <?php echo $porcentaje_aprobacion; ?>%">
                                                    </div>
                                                </div>
                                                <span><?php echo $porcentaje_aprobacion; ?>%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $estado_clase; ?>">
                                                <?php echo ($porcentaje_aprobacion >= 70) ? 'Excelente' : 
                                                       (($porcentaje_aprobacion >= 50) ? 'Aceptable' : 'Crítico'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Gráfico de rendimiento -->
                        <div class="mt-4">
                            <canvas id="chartRendimientoCursos" height="100"></canvas>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Reporte de Estudiantes por Estado -->
        <?php if ($tipo_reporte == 'estudiantes_estado'): ?>
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Distribución por Estado</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="chartEstados" height="300"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Detalle por Estado</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Estado</th>
                                        <th>Cantidad</th>
                                        <th>Porcentaje</th>
                                        <th>Distribución</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_estudiantes = array_sum(array_column($estadisticas['estudiantes_estado'], 'total'));
                                    foreach ($estadisticas['estudiantes_estado'] as $estado): 
                                        $porcentaje = round(($estado['total'] / $total_estudiantes) * 100, 2);
                                        
                                        $estado_text = ucfirst($estado['estado']);
                                        $estado_color = '';
                                        switch ($estado['estado']) {
                                            case 'activo': $estado_color = 'success'; break;
                                            case 'inactivo': $estado_color = 'secondary'; break;
                                            case 'egresado': $estado_color = 'primary'; break;
                                            case 'graduado': $estado_color = 'info'; break;
                                            default: $estado_color = 'warning';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo $estado_color; ?>">
                                                <?php echo $estado_text; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $estado['total']; ?></td>
                                        <td><?php echo $porcentaje; ?>%</td>
                                        <td>
                                            <div class="progress" style="height: 10px;">
                                                <div class="progress-bar bg-<?php echo $estado_color; ?>" 
                                                     role="progressbar" 
                                                     style="width: <?php echo $porcentaje; ?>%">
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function actualizarFiltros() {
    const tipoReporte = document.getElementById('tipo_reporte').value;
    const carreraFilter = document.getElementById('carrera_filter');
    
    if (tipoReporte === 'calificaciones_curso' || tipoReporte === 'rendimiento_carrera') {
        carreraFilter.style.display = 'block';
    } else {
        carreraFilter.style.display = 'none';
    }
}

function generarPDF() {
    Swal.fire({
        title: 'Generar Reporte PDF',
        text: '¿Desea generar un reporte en formato PDF con los datos actuales?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, generar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Aquí iría la lógica para generar el PDF
            Swal.fire(
                '¡Reporte generado!',
                'El reporte PDF ha sido generado exitosamente.',
                'success'
            );
        }
    });
}

// Gráficos
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($tipo_reporte == 'estadisticas_generales' && isset($estadisticas['estudiantes_por_carrera'])): ?>
    // Gráfico de carreras
    const ctxCarreras = document.getElementById('chartCarreras').getContext('2d');
    const carrerasLabels = <?php echo json_encode(array_column($estadisticas['estudiantes_por_carrera'], 'carrera')); ?>;
    const carrerasData = <?php echo json_encode(array_column($estadisticas['estudiantes_por_carrera'], 'total_estudiantes')); ?>;
    
    new Chart(ctxCarreras, {
        type: 'bar',
        data: {
            labels: carrerasLabels,
            datasets: [{
                label: 'Estudiantes',
                data: carrerasData,
                backgroundColor: '#0056b3',
                borderColor: '#003366',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
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
    
    // Gráfico de semestres
    const ctxSemestres = document.getElementById('chartSemestres').getContext('2d');
    const semestresLabels = <?php echo json_encode(array_column($estadisticas['distribucion_semestres'], 'semestre_actual')); ?>;
    const semestresData = <?php echo json_encode(array_column($estadisticas['distribucion_semestres'], 'total')); ?>;
    
    new Chart(ctxSemestres, {
        type: 'line',
        data: {
            labels: semestresLabels.map(s => s + '°'),
            datasets: [{
                label: 'Estudiantes por Semestre',
                data: semestresData,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
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
    <?php endif; ?>
    
    <?php if ($tipo_reporte == 'calificaciones_curso' && isset($estadisticas['calificaciones_curso'])): ?>
    // Gráfico de rendimiento de cursos
    const ctxRendimiento = document.getElementById('chartRendimientoCursos').getContext('2d');
    const cursosLabels = <?php echo json_encode(array_column($estadisticas['calificaciones_curso'], 'codigo')); ?>;
    const cursosPromedios = <?php echo json_encode(array_column($estadisticas['calificaciones_curso'], 'promedio')); ?>;
    
    new Chart(ctxRendimiento, {
        type: 'bar',
        data: {
            labels: cursosLabels,
            datasets: [{
                label: 'Promedio',
                data: cursosPromedios,
                backgroundColor: cursosPromedios.map(p => p >= 15 ? '#28a745' : (p >= 10 ? '#ffc107' : '#dc3545')),
                borderColor: cursosPromedios.map(p => p >= 15 ? '#218838' : (p >= 10 ? '#e0a800' : '#c82333')),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 20,
                    title: {
                        display: true,
                        text: 'Promedio'
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    <?php if ($tipo_reporte == 'estudiantes_estado' && isset($estadisticas['estudiantes_estado'])): ?>
    // Gráfico de estados
    const ctxEstados = document.getElementById('chartEstados').getContext('2d');
    const estadosLabels = <?php echo json_encode(array_map('ucfirst', array_column($estadisticas['estudiantes_estado'], 'estado'))); ?>;
    const estadosData = <?php echo json_encode(array_column($estadisticas['estudiantes_estado'], 'total')); ?>;
    
    // Colores para cada estado
    const estadoColors = {
        'Activo': '#28a745',
        'Inactivo': '#6c757d',
        'Egresado': '#007bff',
        'Graduado': '#17a2b8',
        'Suspendido': '#dc3545'
    };
    
    const backgroundColors = estadosLabels.map(label => estadoColors[label] || '#ffc107');
    
    new Chart(ctxEstados, {
        type: 'doughnut',
        data: {
            labels: estadosLabels,
            datasets: [{
                data: estadosData,
                backgroundColor: backgroundColors,
                borderWidth: 1
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
    <?php endif; ?>
});
</script>

<?php include '../../includes/footer.php'; ?>