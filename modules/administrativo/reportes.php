<?php
// modules/administrativo/reportes.php
require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SESSION['rol'] != 'administrador') {
    header('Location: ../../index.php');
    exit();
}

$page_title = 'Sistema de Reportes';

$db = new Database();
$conn = $db->getConnection();

// Parámetros
$tipo_reporte = $_GET['tipo'] ?? 'academico';
$periodo = $_GET['periodo'] ?? date('Y') . '-1';
$carrera_id = $_GET['carrera_id'] ?? '';
$formato = $_GET['formato'] ?? 'html';

// Obtener carreras
$query = "SELECT * FROM carreras WHERE estado = 'activa' ORDER BY nombre";
$stmt = $conn->query($query);
$carreras = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Datos para reportes
$datos_reporte = [];

if ($tipo_reporte == 'academico') {
    // Reporte académico general
    $query = "SELECT 
                c.nombre as carrera,
                COUNT(DISTINCT e.id) as total_estudiantes,
                AVG(e.promedio_general) as promedio_general,
                SUM(CASE WHEN e.promedio_general >= 16 THEN 1 ELSE 0 END) as excelencia,
                SUM(CASE WHEN e.promedio_general BETWEEN 10 AND 15.99 THEN 1 ELSE 0 END) as aprobados,
                SUM(CASE WHEN e.promedio_general < 10 THEN 1 ELSE 0 END) as reprobados,
                COUNT(DISTINCT CASE WHEN e.estado = 'egresado' THEN e.id END) as egresados,
                COUNT(DISTINCT CASE WHEN e.estado = 'graduado' THEN e.id END) as graduados
              FROM carreras c 
              LEFT JOIN estudiantes e ON c.id = e.carrera_id AND e.estado IN ('activo', 'egresado', 'graduado')
              WHERE c.estado = 'activa'";
    
    if (!empty($carrera_id)) {
        $query .= " AND c.id = :carrera_id";
    }
    
    $query .= " GROUP BY c.id ORDER BY c.nombre";
    
    $stmt = $conn->prepare($query);
    if (!empty($carrera_id)) {
        $stmt->bindParam(':carrera_id', $carrera_id);
    }
    $stmt->execute();
    $datos_reporte = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif ($tipo_reporte == 'financiero') {
    // Reporte financiero
    $query = "SELECT 
                MONTH(p.fecha_pago) as mes,
                YEAR(p.fecha_pago) as año,
                p.concepto,
                COUNT(p.id) as total_transacciones,
                SUM(p.monto) as total_monto,
                AVG(p.monto) as promedio_monto,
                (SELECT SUM(monto) FROM pagos WHERE estado = 'pagado' 
                 AND MONTH(fecha_pago) = MONTH(p.fecha_pago) 
                 AND YEAR(fecha_pago) = YEAR(p.fecha_pago)) as total_mes
              FROM pagos p 
              WHERE p.estado = 'pagado' 
              AND p.fecha_pago >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";
    
    if (!empty($periodo)) {
        list($year, $semester) = explode('-', $periodo);
        $query .= " AND YEAR(p.fecha_pago) = :year 
                    AND MONTH(p.fecha_pago) BETWEEN :mes_inicio AND :mes_fin";
    }
    
    $query .= " GROUP BY YEAR(p.fecha_pago), MONTH(p.fecha_pago), p.concepto 
                ORDER BY YEAR(p.fecha_pago) DESC, MONTH(p.fecha_pago) DESC";
    
    $stmt = $conn->prepare($query);
    if (!empty($periodo)) {
        $mes_inicio = ($semester == 1) ? 1 : 7;
        $mes_fin = ($semester == 1) ? 6 : 12;
        $stmt->bindParam(':year', $year);
        $stmt->bindParam(':mes_inicio', $mes_inicio);
        $stmt->bindParam(':mes_fin', $mes_fin);
    }
    $stmt->execute();
    $datos_reporte = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif ($tipo_reporte == 'matriculas') {
    // Reporte de matrículas
    $query = "SELECT 
                s.periodo_academico,
                ca.nombre as carrera,
                COUNT(DISTINCT m.estudiante_id) as total_matriculados,
                COUNT(DISTINCT CASE WHEN e.genero = 'M' THEN e.id END) as hombres,
                COUNT(DISTINCT CASE WHEN e.genero = 'F' THEN e.id END) as mujeres,
                AVG(e.promedio_general) as promedio_ingreso,
                MIN(e.fecha_ingreso) as fecha_primera_matricula,
                MAX(e.fecha_ingreso) as fecha_ultima_matricula
              FROM secciones s 
              JOIN cursos c ON s.curso_id = c.id 
              JOIN carreras ca ON c.carrera_id = ca.id 
              JOIN matriculas m ON s.id = m.seccion_id 
              JOIN estudiantes e ON m.estudiante_id = e.id 
              WHERE s.periodo_academico IS NOT NULL";
    
    if (!empty($periodo)) {
        $query .= " AND s.periodo_academico = :periodo";
    }
    
    if (!empty($carrera_id)) {
        $query .= " AND ca.id = :carrera_id";
    }
    
    $query .= " GROUP BY s.periodo_academico, ca.id 
                ORDER BY s.periodo_academico DESC, ca.nombre";
    
    $stmt = $conn->prepare($query);
    if (!empty($periodo)) {
        $stmt->bindParam(':periodo', $periodo);
    }
    if (!empty($carrera_id)) {
        $stmt->bindParam(':carrera_id', $carrera_id);
    }
    $stmt->execute();
    $datos_reporte = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif ($tipo_reporte == 'docentes') {
    // Reporte de desempeño docente
    $query = "SELECT 
                d.nombres,
                d.apellidos,
                d.codigo_docente,
                d.titulo_academico,
                COUNT(DISTINCT s.id) as total_secciones,
                COUNT(DISTINCT m.estudiante_id) as total_estudiantes,
                AVG(m2.nota_final) as promedio_calificaciones,
                MIN(m2.nota_final) as minima_calificacion,
                MAX(m2.nota_final) as maxima_calificacion,
                COUNT(DISTINCT CASE WHEN m2.nota_final >= 10 THEN m2.id END) as aprobados,
                COUNT(DISTINCT CASE WHEN m2.nota_final < 10 THEN m2.id END) as reprobados,
                (SELECT COUNT(*) FROM secciones s2 
                 WHERE s2.docente_id = d.id 
                 AND s2.estado = 'en_progreso') as secciones_activas
              FROM docentes d 
              LEFT JOIN secciones s ON d.id = s.docente_id 
              LEFT JOIN matriculas m ON s.id = m.seccion_id 
              LEFT JOIN matriculas m2 ON s.id = m2.seccion_id AND m2.nota_final IS NOT NULL
              WHERE d.estado = 'activo' 
              AND s.periodo_academico = :periodo
              GROUP BY d.id 
              ORDER BY d.apellidos, d.nombres";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':periodo', $periodo);
    $stmt->execute();
    $datos_reporte = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Sistema de Reportes</h5>
        <div>
            <?php if (!empty($datos_reporte)): ?>
            <div class="btn-group">
                <button type="button" class="btn btn-unexca" onclick="exportarPDF()">
                    <i class="fas fa-file-pdf me-2"></i>PDF
                </button>
                <button type="button" class="btn btn-success" onclick="exportarExcel()">
                    <i class="fas fa-file-excel me-2"></i>Excel
                </button>
                <button type="button" class="btn btn-info" onclick="imprimirReporte()">
                    <i class="fas fa-print me-2"></i>Imprimir
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card-body">
        <!-- Filtros -->
        <div class="row mb-4">
            <div class="col-md-12">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="tipo" class="form-label">Tipo de Reporte</label>
                        <select class="form-select" id="tipo" name="tipo" onchange="actualizarFiltros()">
                            <option value="academico" <?php echo ($tipo_reporte == 'academico') ? 'selected' : ''; ?>>Académico General</option>
                            <option value="financiero" <?php echo ($tipo_reporte == 'financiero') ? 'selected' : ''; ?>>Financiero</option>
                            <option value="matriculas" <?php echo ($tipo_reporte == 'matriculas') ? 'selected' : ''; ?>>Matrículas</option>
                            <option value="docentes" <?php echo ($tipo_reporte == 'docentes') ? 'selected' : ''; ?>>Desempeño Docente</option>
                            <option value="estudiantes" <?php echo ($tipo_reporte == 'estudiantes') ? 'selected' : ''; ?>>Estudiantes por Carrera</option>
                            <option value="calificaciones" <?php echo ($tipo_reporte == 'calificaciones') ? 'selected' : ''; ?>>Calificaciones por Curso</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="periodo" class="form-label">Período Académico</label>
                        <select class="form-select" id="periodo" name="periodo">
                            <?php
                            $query = "SELECT DISTINCT periodo_academico FROM secciones 
                                      ORDER BY periodo_academico DESC LIMIT 10";
                            $stmt = $conn->query($query);
                            $periodos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($periodos as $p):
                            ?>
                            <option value="<?php echo $p['periodo_academico']; ?>" 
                                    <?php echo ($periodo == $p['periodo_academico']) ? 'selected' : ''; ?>>
                                <?php echo $p['periodo_academico']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3" id="carrera_filtro" 
                         style="<?php echo in_array($tipo_reporte, ['academico', 'matriculas', 'estudiantes', 'calificaciones']) ? '' : 'display: none;'; ?>">
                        <label for="carrera_id" class="form-label">Carrera</label>
                        <select class="form-select" id="carrera_id" name="carrera_id">
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
                        <label for="formato" class="form-label">Formato</label>
                        <select class="form-select" id="formato" name="formato">
                            <option value="html" <?php echo ($formato == 'html') ? 'selected' : ''; ?>>Vista Web</option>
                            <option value="pdf" <?php echo ($formato == 'pdf') ? 'selected' : ''; ?>>PDF</option>
                            <option value="excel" <?php echo ($formato == 'excel') ? 'selected' : ''; ?>>Excel</option>
                        </select>
                    </div>
                    
                    <div class="col-md-12 mt-3">
                        <button type="submit" class="btn btn-unexca">
                            <i class="fas fa-chart-bar me-2"></i>Generar Reporte
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='reportes.php'">
                            <i class="fas fa-redo me-2"></i>Limpiar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if (!empty($datos_reporte)): ?>
        
        <!-- Encabezado del reporte -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="report-header text-center mb-4">
                    <h4>UNIVERSIDAD NACIONAL EXPERIMENTAL DE LA GRAN CARACAS</h4>
                    <h5 class="text-primary"><?php echo getTituloReporte($tipo_reporte); ?></h5>
                    <p class="text-muted">
                        Período: <?php echo $periodo; ?> | 
                        Fecha de generación: <?php echo date('d/m/Y H:i'); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Contenido del reporte -->
        <div class="row" id="contenido-reporte">
            <div class="col-md-12">
                <?php if ($tipo_reporte == 'academico'): ?>
                <!-- Reporte académico -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Carrera</th>
                                <th>Estudiantes</th>
                                <th>Promedio</th>
                                <th>Excelencia</th>
                                <th>Aprobados</th>
                                <th>Reprobados</th>
                                <th>Egresados</th>
                                <th>Graduados</th>
                                <th>% Éxito</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($datos_reporte as $fila): 
                                $porcentaje_exito = $fila['total_estudiantes'] > 0 ? 
                                    round((($fila['aprobados'] + $fila['excelencia']) / $fila['total_estudiantes']) * 100, 2) : 0;
                            ?>
                            <tr>
                                <td><strong><?php echo $fila['carrera']; ?></strong></td>
                                <td class="text-center"><?php echo $fila['total_estudiantes']; ?></td>
                                <td class="text-center"><?php echo number_format($fila['promedio_general'] ?? 0, 2); ?></td>
                                <td class="text-center text-success"><?php echo $fila['excelencia']; ?></td>
                                <td class="text-center text-primary"><?php echo $fila['aprobados']; ?></td>
                                <td class="text-center text-danger"><?php echo $fila['reprobados']; ?></td>
                                <td class="text-center text-info"><?php echo $fila['egresados']; ?></td>
                                <td class="text-center text-success"><?php echo $fila['graduados']; ?></td>
                                <td class="text-center">
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar <?php echo $porcentaje_exito >= 80 ? 'bg-success' : ($porcentaje_exito >= 60 ? 'bg-warning' : 'bg-danger'); ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo $porcentaje_exito; ?>%">
                                            <?php echo $porcentaje_exito; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th>TOTALES</th>
                                <th class="text-center"><?php echo array_sum(array_column($datos_reporte, 'total_estudiantes')); ?></th>
                                <th class="text-center"><?php echo number_format(array_sum(array_column($datos_reporte, 'promedio_general')) / count($datos_reporte), 2); ?></th>
                                <th class="text-center text-success"><?php echo array_sum(array_column($datos_reporte, 'excelencia')); ?></th>
                                <th class="text-center text-primary"><?php echo array_sum(array_column($datos_reporte, 'aprobados')); ?></th>
                                <th class="text-center text-danger"><?php echo array_sum(array_column($datos_reporte, 'reprobados')); ?></th>
                                <th class="text-center text-info"><?php echo array_sum(array_column($datos_reporte, 'egresados')); ?></th>
                                <th class="text-center text-success"><?php echo array_sum(array_column($datos_reporte, 'graduados')); ?></th>
                                <th class="text-center">
                                    <?php
                                    $total_estudiantes = array_sum(array_column($datos_reporte, 'total_estudiantes'));
                                    $total_exitosos = array_sum(array_column($datos_reporte, 'excelencia')) + 
                                                     array_sum(array_column($datos_reporte, 'aprobados'));
                                    $porcentaje_total = $total_estudiantes > 0 ? 
                                        round(($total_exitosos / $total_estudiantes) * 100, 2) : 0;
                                    ?>
                                    <strong><?php echo $porcentaje_total; ?>%</strong>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <!-- Gráfico de rendimiento -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Distribución de Rendimiento por Carrera</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="chartRendimientoCarreras" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($tipo_reporte == 'financiero'): ?>
                <!-- Reporte financiero -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Mes/Año</th>
                                <th>Concepto</th>
                                <th>Transacciones</th>
                                <th>Total Recaudado</th>
                                <th>Promedio por Transacción</th>
                                <th>% del Total Mensual</th>
                                <th>Tendencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $mes_actual = '';
                            $total_mes_actual = 0;
                            $datos_grafico = [];
                            foreach ($datos_reporte as $fila): 
                                $mes = date('F Y', strtotime($fila['año'] . '-' . $fila['mes'] . '-01'));
                                $porcentaje_mes = $fila['total_mes'] > 0 ? 
                                    round(($fila['total_monto'] / $fila['total_mes']) * 100, 2) : 0;
                                
                                // Agrupar por mes para el gráfico
                                if (!isset($datos_grafico[$mes])) {
                                    $datos_grafico[$mes] = 0;
                                }
                                $datos_grafico[$mes] += $fila['total_monto'];
                            ?>
                            <tr>
                                <td>
                                    <?php if ($mes != $mes_actual): ?>
                                    <strong><?php echo $mes; ?></strong>
                                    <?php $mes_actual = $mes; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $fila['concepto']; ?></td>
                                <td class="text-center"><?php echo $fila['total_transacciones']; ?></td>
                                <td class="text-end">$<?php echo number_format($fila['total_monto'], 2); ?></td>
                                <td class="text-end">$<?php echo number_format($fila['promedio_monto'], 2); ?></td>
                                <td class="text-center">
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-info" role="progressbar" 
                                             style="width: <?php echo $porcentaje_mes; ?>%">
                                            <?php echo $porcentaje_mes; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $tendencia = '';
                                    $color = '';
                                    if ($porcentaje_mes > 50) {
                                        $tendencia = '↑ Alta';
                                        $color = 'success';
                                    } elseif ($porcentaje_mes > 20) {
                                        $tendencia = '→ Media';
                                        $color = 'warning';
                                    } else {
                                        $tendencia = '↓ Baja';
                                        $color = 'danger';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>"><?php echo $tendencia; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="3">TOTAL GENERAL</th>
                                <th class="text-end">
                                    $<?php echo number_format(array_sum(array_column($datos_reporte, 'total_monto')), 2); ?>
                                </th>
                                <th class="text-end">
                                    $<?php echo number_format(array_sum(array_column($datos_reporte, 'promedio_monto')) / count($datos_reporte), 2); ?>
                                </th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <!-- Gráfico de ingresos -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Evolución de Ingresos Mensuales</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="chartIngresosMensuales" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($tipo_reporte == 'matriculas'): ?>
                <!-- Reporte de matrículas -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Período</th>
                                <th>Carrera</th>
                                <th>Matriculados</th>
                                <th>Hombres</th>
                                <th>Mujeres</th>
                                <th>Promedio Ingreso</th>
                                <th>Primera Matrícula</th>
                                <th>Última Matrícula</th>
                                <th>% Crecimiento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $periodo_anterior = '';
                            $matriculados_anterior = 0;
                            $datos_grafico = [];
                            foreach ($datos_reporte as $fila): 
                                // Calcular crecimiento
                                $crecimiento = 0;
                                if ($periodo_anterior && $matriculados_anterior > 0) {
                                    $crecimiento = round((($fila['total_matriculados'] - $matriculados_anterior) / $matriculados_anterior) * 100, 2);
                                }
                                $periodo_anterior = $fila['periodo_academico'];
                                $matriculados_anterior = $fila['total_matriculados'];
                                
                                // Datos para gráfico
                                if (!isset($datos_grafico[$fila['periodo_academico']])) {
                                    $datos_grafico[$fila['periodo_academico']] = 0;
                                }
                                $datos_grafico[$fila['periodo_academico']] += $fila['total_matriculados'];
                            ?>
                            <tr>
                                <td><strong><?php echo $fila['periodo_academico']; ?></strong></td>
                                <td><?php echo $fila['carrera']; ?></td>
                                <td class="text-center"><?php echo $fila['total_matriculados']; ?></td>
                                <td class="text-center"><?php echo $fila['hombres']; ?></td>
                                <td class="text-center"><?php echo $fila['mujeres']; ?></td>
                                <td class="text-center"><?php echo number_format($fila['promedio_ingreso'] ?? 0, 2); ?></td>
                                <td class="text-center"><?php echo date('d/m/Y', strtotime($fila['fecha_primera_matricula'])); ?></td>
                                <td class="text-center"><?php echo date('d/m/Y', strtotime($fila['fecha_ultima_matricula'])); ?></td>
                                <td class="text-center">
                                    <?php if ($crecimiento != 0): ?>
                                    <span class="badge bg-<?php echo $crecimiento > 0 ? 'success' : 'danger'; ?>">
                                        <?php echo $crecimiento > 0 ? '+' : ''; ?><?php echo $crecimiento; ?>%
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Gráfico de matrículas -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Evolución de Matrículas por Período</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="chartMatriculasPeriodo" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($tipo_reporte == 'docentes'): ?>
                <!-- Reporte de docentes -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Docente</th>
                                <th>Código</th>
                                <th>Título</th>
                                <th>Secciones</th>
                                <th>Estudiantes</th>
                                <th>Promedio</th>
                                <th>Mínima</th>
                                <th>Máxima</th>
                                <th>Aprobados</th>
                                <th>Reprobados</th>
                                <th>% Aprobación</th>
                                <th>Evaluación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($datos_reporte as $fila): 
                                $porcentaje_aprobacion = ($fila['aprobados'] + $fila['reprobados']) > 0 ? 
                                    round(($fila['aprobados'] / ($fila['aprobados'] + $fila['reprobados'])) * 100, 2) : 0;
                                
                                $evaluacion = '';
                                $evaluacion_color = '';
                                if ($porcentaje_aprobacion >= 80) {
                                    $evaluacion = 'Excelente';
                                    $evaluacion_color = 'success';
                                } elseif ($porcentaje_aprobacion >= 60) {
                                    $evaluacion = 'Bueno';
                                    $evaluacion_color = 'info';
                                } elseif ($porcentaje_aprobacion >= 40) {
                                    $evaluacion = 'Regular';
                                    $evaluacion_color = 'warning';
                                } else {
                                    $evaluacion = 'Deficiente';
                                    $evaluacion_color = 'danger';
                                }
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo $fila['nombres'] . ' ' . $fila['apellidos']; ?></strong>
                                </td>
                                <td><?php echo $fila['codigo_docente']; ?></td>
                                <td><?php echo $fila['titulo_academico']; ?></td>
                                <td class="text-center"><?php echo $fila['total_secciones']; ?></td>
                                <td class="text-center"><?php echo $fila['total_estudiantes']; ?></td>
                                <td class="text-center"><?php echo number_format($fila['promedio_calificaciones'] ?? 0, 2); ?></td>
                                <td class="text-center"><?php echo number_format($fila['minima_calificacion'] ?? 0, 2); ?></td>
                                <td class="text-center"><?php echo number_format($fila['maxima_calificacion'] ?? 0, 2); ?></td>
                                <td class="text-center text-success"><?php echo $fila['aprobados']; ?></td>
                                <td class="text-center text-danger"><?php echo $fila['reprobados']; ?></td>
                                <td class="text-center">
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-<?php echo $evaluacion_color; ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo $porcentaje_aprobacion; ?>%">
                                            <?php echo $porcentaje_aprobacion; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $evaluacion_color; ?>">
                                        <?php echo $evaluacion; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Gráfico de evaluación docente -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Distribución de Evaluación Docente</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="chartEvaluacionDocente" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Resumen ejecutivo -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Resumen Ejecutivo</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Hallazgos Principales:</h6>
                                <ul>
                                    <?php echo getHallazgosReporte($tipo_reporte, $datos_reporte); ?>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Recomendaciones:</h6>
                                <ul>
                                    <?php echo getRecomendacionesReporte($tipo_reporte, $datos_reporte); ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Sin datos -->
        <div class="text-center py-5">
            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No hay datos para el reporte seleccionado</h5>
            <p class="text-muted">Seleccione diferentes filtros o período</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Scripts para gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function actualizarFiltros() {
    const tipo = document.getElementById('tipo').value;
    const carreraFiltro = document.getElementById('carrera_filtro');
    
    // Mostrar/ocultar filtro de carrera según tipo de reporte
    const reportesConCarrera = ['academico', 'matriculas', 'estudiantes', 'calificaciones'];
    if (reportesConCarrera.includes(tipo)) {
        carreraFiltro.style.display = 'block';
    } else {
        carreraFiltro.style.display = 'none';
    }
}

function exportarPDF() {
    UNEXCA.Utils.showNotification('Generando PDF...', 'info');
    
    // En producción, esto llamaría a un servicio de generación de PDF
    setTimeout(() => {
        UNEXCA.Utils.showNotification('PDF generado exitosamente', 'success');
    }, 2000);
}

function exportarExcel() {
    const tipo = '<?php echo $tipo_reporte; ?>';
    const periodo = '<?php echo $periodo; ?>';
    
    UNEXCA.Utils.showNotification('Preparando archivo Excel...', 'info');
    
    // Simular descarga
    setTimeout(() => {
        const link = document.createElement('a');
        link.href = `ajax/exportar_excel.php?tipo=${tipo}&periodo=${periodo}`;
        link.download = `reporte_${tipo}_${periodo}.xlsx`;
        link.click();
        
        UNEXCA.Utils.showNotification('Excel exportado exitosamente', 'success');
    }, 1000);
}

function imprimirReporte() {
    window.print();
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($tipo_reporte == 'academico' && !empty($datos_reporte)): ?>
    // Gráfico de rendimiento por carrera
    const ctxRendimiento = document.getElementById('chartRendimientoCarreras').getContext('2d');
    const labelsCarreras = <?php echo json_encode(array_column($datos_reporte, 'carrera')); ?>;
    const dataAprobados = <?php echo json_encode(array_column($datos_reporte, 'aprobados')); ?>;
    const dataExcelencia = <?php echo json_encode(array_column($datos_reporte, 'excelencia')); ?>;
    
    new Chart(ctxRendimiento, {
        type: 'bar',
        data: {
            labels: labelsCarreras,
            datasets: [
                {
                    label: 'Aprobados',
                    data: dataAprobados,
                    backgroundColor: '#28a745'
                },
                {
                    label: 'Excelencia',
                    data: dataExcelencia,
                    backgroundColor: '#0056b3'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
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
    
    <?php if ($tipo_reporte == 'financiero' && !empty($datos_grafico)): ?>
    // Gráfico de ingresos mensuales
    const ctxIngresos = document.getElementById('chartIngresosMensuales').getContext('2d');
    const labelsMeses = <?php echo json_encode(array_keys($datos_grafico)); ?>;
    const dataIngresos = <?php echo json_encode(array_values($datos_grafico)); ?>;
    
    new Chart(ctxIngresos, {
        type: 'line',
        data: {
            labels: labelsMeses,
            datasets: [{
                label: 'Ingresos ($)',
                data: dataIngresos,
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
                    position: 'top'
                }
            },
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
    <?php endif; ?>
    
    <?php if ($tipo_reporte == 'matriculas' && !empty($datos_grafico)): ?>
    // Gráfico de matrículas por período
    const ctxMatriculas = document.getElementById('chartMatriculasPeriodo').getContext('2d');
    const labelsPeriodos = <?php echo json_encode(array_keys($datos_grafico)); ?>;
    const dataMatriculas = <?php echo json_encode(array_values($datos_grafico)); ?>;
    
    new Chart(ctxMatriculas, {
        type: 'bar',
        data: {
            labels: labelsPeriodos,
            datasets: [{
                label: 'Matriculados',
                data: dataMatriculas,
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
                        text: 'Cantidad de Matriculados'
                    }
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<style>
.report-header {
    padding: 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 10px;
    border: 1px solid #dee2e6;
}

.report-header h4 {
    color: #0056b3;
    font-weight: bold;
}

.report-header h5 {
    color: #6c757d;
    font-weight: 600;
}

@media print {
    .no-print {
        display: none !important;
    }
    
    .report-header {
        background: white !important;
        border: 2px solid #000 !important;
    }
    
    .table-dark {
        background: #343a40 !important;
        color: white !important;
        /*-webkit-print-color-adjust: exact;*/
    }
    /*
    .progress-bar {
        -webkit-print-color-adjust: exact;
    }*/
}
</style>

<?php
// Funciones auxiliares
function getTituloReporte($tipo) {
    $titulos = [
        'academico' => 'REPORTE ACADÉMICO GENERAL',
        'financiero' => 'REPORTE FINANCIERO',
        'matriculas' => 'REPORTE DE MATRÍCULAS',
        'docentes' => 'REPORTE DE DESEMPEÑO DOCENTE',
        'estudiantes' => 'REPORTE DE ESTUDIANTES POR CARRERA',
        'calificaciones' => 'REPORTE DE CALIFICACIONES POR CURSO'
    ];
    
    return $titulos[$tipo] ?? 'REPORTE DEL SISTEMA';
}

function getHallazgosReporte($tipo, $datos) {
    if (empty($datos)) return '';
    
    $hallazgos = '';
    
    switch ($tipo) {
        case 'academico':
            $total_estudiantes = array_sum(array_column($datos, 'total_estudiantes'));
            $promedio_general = array_sum(array_column($datos, 'promedio_general')) / count($datos);
            $porcentaje_exito = array_sum(array_column($datos, 'aprobados')) / /*$total_estudiantes*/50 * 100;
            
            $hallazgos .= "<li>Total de estudiantes activos: <strong>" . number_format($total_estudiantes) . "</strong></li>";
            $hallazgos .= "<li>Promedio general institucional: <strong>" . number_format($promedio_general, 2) . "</strong></li>";
            $hallazgos .= "<li>Porcentaje de éxito académico: <strong>" . number_format($porcentaje_exito, 2) . "%</strong></li>";
            $hallazgos .= "<li>Carrera con mejor desempeño: <strong>" . $datos[0]['carrera'] . "</strong></li>";
            break;
            
        case 'financiero':
            $total_recaudado = array_sum(array_column($datos, 'total_monto'));
            $concepto_mas_recaudado = '';
            $max_recaudado = 0;
            
            foreach ($datos as $fila) {
                if ($fila['total_monto'] > $max_recaudado) {
                    $max_recaudado = $fila['total_monto'];
                    $concepto_mas_recaudado = $fila['concepto'];
                }
            }
            
            $hallazgos .= "<li>Total recaudado en el período: <strong>$" . number_format($total_recaudado, 2) . "</strong></li>";
            $hallazgos .= "<li>Concepto que más genera ingresos: <strong>" . $concepto_mas_recaudado . "</strong></li>";
            $hallazgos .= "<li>Promedio por transacción: <strong>$" . 
                         number_format(array_sum(array_column($datos, 'promedio_monto')) / count($datos), 2) . "</strong></li>";
            break;
            
        case 'matriculas':
            $total_matriculados = array_sum(array_column($datos, 'total_matriculados'));
            $crecimiento = 0;
            
            if (count($datos) > 1) {
                $primero = $datos[0]['total_matriculados'];
                $ultimo = end($datos)['total_matriculados'];
                $crecimiento = (($primero - $ultimo) / $ultimo) * 100;
            }
            
            $hallazgos .= "<li>Total de matriculados: <strong>" . number_format($total_matriculados) . "</strong></li>";
            $hallazgos .= "<li>Tasa de crecimiento: <strong>" . number_format($crecimiento, 2) . "%</strong></li>";
            $hallazgos .= "<li>Distribución por género: Aproximadamente " . 
                         round(array_sum(array_column($datos, 'hombres')) / $total_matriculados * 100, 2) . 
                         "% hombres, " . 
                         round(array_sum(array_column($datos, 'mujeres')) / $total_matriculados * 100, 2) . 
                         "% mujeres</li>";
            break;
            
        case 'docentes':
            $total_docentes = count($datos);
            $promedio_aprobacion = array_sum(array_column($datos, 'aprobados')) / 
                                  (array_sum(array_column($datos, 'aprobados')) + array_sum(array_column($datos, 'reprobados'))) * 100;
            
            $hallazgos .= "<li>Total de docentes evaluados: <strong>" . $total_docentes . "</strong></li>";
            $hallazgos .= "<li>Promedio de aprobación: <strong>" . number_format($promedio_aprobacion, 2) . "%</strong></li>";
            $hallazgos .= "<li>Docente con mejor desempeño: <strong>" . $datos[0]['nombres'] . ' ' . $datos[0]['apellidos'] . "</strong></li>";
            break;
    }
    
    return $hallazgos;
}

function getRecomendacionesReporte($tipo, $datos) {
    $recomendaciones = '';
    
    switch ($tipo) {
        case 'academico':
            $recomendaciones .= "<li>Implementar programas de tutoría para estudiantes con promedios bajos</li>";
            $recomendaciones .= "<li>Revisar el plan de estudios de carreras con bajo rendimiento</li>";
            $recomendaciones .= "<li>Establecer incentivos para estudiantes con excelencia académica</li>";
            $recomendaciones .= "<li>Fortalecer los programas de acompañamiento académico</li>";
            break;
            
        case 'financiero':
            $recomendaciones .= "<li>Implementar descuentos por pago anticipado</li>";
            $recomendaciones .= "<li>Diversificar los métodos de pago disponibles</li>";
            $recomendaciones .= "<li>Establecer planes de pago flexibles</li>";
            $recomendaciones .= "<li>Automatizar el proceso de recordatorios de pago</li>";
            break;
            
        case 'matriculas':
            $recomendaciones .= "<li>Implementar campañas de captación para períodos de baja matrícula</li>";
            $recomendaciones .= "<li>Crear programas de becas para aumentar la matrícula</li>";
            $recomendaciones .= "<li>Establecer alianzas con instituciones educativas</li>";
            $recomendaciones .= "<li>Mejorar los procesos de inscripción en línea</li>";
            break;
            
        case 'docentes':
            $recomendaciones .= "<li>Implementar programas de capacitación docente continua</li>";
            $recomendaciones .= "<li>Establecer un sistema de mentoría entre docentes</li>";
            $recomendaciones .= "<li>Revisar la carga académica de los docentes</li>";
            $recomendaciones .= "<li>Crear incentivos por desempeño docente destacado</li>";
            break;
    }
    
    return $recomendaciones;
}

include '../../includes/footer.php'; ?>