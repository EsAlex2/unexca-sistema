<?php
// modules/administrativo/seccion_detalle.php
require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SESSION['rol'] != 'administrador') {
    header('Location: ../../index.php');
    exit();
}

$page_title = 'Detalle de Sección';

$db = new Database();
$conn = $db->getConnection();

$seccion_id = $_GET['id'] ?? 0;

if (!$seccion_id) {
    header('Location: secciones.php');
    exit();
}

// Obtener información completa de la sección
$query = "SELECT s.*, 
                 c.codigo as curso_codigo, c.nombre as curso_nombre, c.descripcion as curso_descripcion,
                 c.creditos, c.semestre, c.horas_teoria, c.horas_practica,
                 ca.nombre as carrera_nombre, ca.codigo as carrera_codigo,
                 d.nombres as docente_nombres, d.apellidos as docente_apellidos,
                 d.email as docente_email, d.telefono as docente_telefono,
                 d.titulo_academico as docente_titulo
          FROM secciones s 
          JOIN cursos c ON s.curso_id = c.id 
          JOIN carreras ca ON c.carrera_id = ca.id 
          LEFT JOIN docentes d ON s.docente_id = d.id 
          WHERE s.id = :id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $seccion_id);
$stmt->execute();
$seccion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seccion) {
    $_SESSION['message'] = 'Sección no encontrada';
    $_SESSION['message_type'] = 'danger';
    header('Location: secciones.php');
    exit();
}

// Obtener estudiantes matriculados
$query = "SELECT m.*, 
                 e.codigo_estudiante, e.nombres, e.apellidos, e.email, e.telefono,
                 e.semestre_actual, e.promedio_general,
                 (SELECT AVG(nota_final) FROM matriculas WHERE estudiante_id = e.id AND nota_final IS NOT NULL) as promedio_estudiante
          FROM matriculas m 
          JOIN estudiantes e ON m.estudiante_id = e.id 
          WHERE m.seccion_id = :id 
          ORDER BY e.apellidos, e.nombres";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $seccion_id);
$stmt->execute();
$estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener tipos de evaluación
$query = "SELECT * FROM tipos_evaluacion WHERE seccion_id = :id ORDER BY orden, id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $seccion_id);
$stmt->execute();
$tipos_evaluacion = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener calificaciones
$calificaciones = [];
if (!empty($estudiantes) && !empty($tipos_evaluacion)) {
    foreach ($estudiantes as $estudiante) {
        $calificaciones[$estudiante['id']] = [];
        foreach ($tipos_evaluacion as $tipo) {
            $query = "SELECT * FROM calificaciones 
                      WHERE estudiante_id = :estudiante_id 
                      AND tipo_evaluacion_id = :tipo_id";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':estudiante_id' => $estudiante['id'],
                ':tipo_id' => $tipo['id']
            ]);
            $calificaciones[$estudiante['id']][$tipo['id']] = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

// Calcular estadísticas
$cupo_porcentaje = $seccion['cupo_maximo'] > 0 ? 
    round((count($estudiantes) / $seccion['cupo_maximo']) * 100, 1) : 0;

// Obtener asistencia promedio
$query = "SELECT 
            COUNT(*) as total_clases,
            SUM(CASE WHEN estado = 'presente' THEN 1 ELSE 0 END) as presentes,
            SUM(CASE WHEN estado = 'ausente' THEN 1 ELSE 0 END) as ausentes,
            SUM(CASE WHEN estado = 'justificado' THEN 1 ELSE 0 END) as justificados
          FROM asistencia 
          WHERE seccion_id = :id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $seccion_id);
$stmt->execute();
$asistencia = $stmt->fetch(PDO::FETCH_ASSOC);

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
        <h5 class="mb-0">Detalle de Sección</h5>
        <div>
            <a href="secciones.php?action=editar&id=<?php echo $seccion_id; ?>" class="btn btn-unexca me-2">
                <i class="fas fa-edit me-2"></i>Editar
            </a>
            <a href="secciones.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Volver
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <!-- Información principal -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <h4 class="text-primary mb-3"><?php echo $seccion['curso_nombre']; ?></h4>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <table class="table table-sm table-borderless">
                                            <tr>
                                                <th class="text-muted" style="width: 40%;">Código Sección:</th>
                                                <td><strong><?php echo $seccion['codigo_seccion']; ?></strong></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Curso:</th>
                                                <td>
                                                    <?php echo $seccion['curso_codigo']; ?> - 
                                                    <?php echo $seccion['curso_nombre']; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Carrera:</th>
                                                <td><?php echo $seccion['carrera_nombre']; ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Créditos:</th>
                                                <td><?php echo $seccion['creditos']; ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Semestre:</th>
                                                <td><?php echo $seccion['semestre']; ?>°</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-sm table-borderless">
                                            <tr>
                                                <th class="text-muted" style="width: 40%;">Período:</th>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $seccion['periodo_academico']; ?></span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Horario:</th>
                                                <td><?php echo $seccion['horario']; ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Aula:</th>
                                                <td>
                                                    <i class="fas fa-<?php echo $seccion['modalidad'] == 'virtual' ? 'laptop' : 'building'; ?> me-1"></i>
                                                    <?php echo $seccion['aula'] ?: 'No definida'; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Modalidad:</th>
                                                <td>
                                                    <span class="badge bg-<?php echo $seccion['modalidad'] == 'virtual' ? 'info' : 'primary'; ?>">
                                                        <?php echo ucfirst($seccion['modalidad']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Estado:</th>
                                                <td>
                                                    <?php
                                                    $estado_class = '';
                                                    $estado_icon = '';
                                                    switch ($seccion['estado']) {
                                                        case 'abierta':
                                                            $estado_class = 'success';
                                                            $estado_icon = 'door-open';
                                                            break;
                                                        case 'cerrada':
                                                            $estado_class = 'secondary';
                                                            $estado_icon = 'door-closed';
                                                            break;
                                                        case 'en_progreso':
                                                            $estado_class = 'primary';
                                                            $estado_icon = 'play-circle';
                                                            break;
                                                        case 'finalizada':
                                                            $estado_class = 'info';
                                                            $estado_icon = 'check-circle';
                                                            break;
                                                        case 'cancelada':
                                                            $estado_class = 'danger';
                                                            $estado_icon = 'times-circle';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge bg-<?php echo $estado_class; ?>">
                                                        <i class="fas fa-<?php echo $estado_icon; ?> me-1"></i>
                                                        <?php echo ucfirst($seccion['estado']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- Fechas -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body py-2">
                                                <small class="text-muted d-block">Fecha Inicio</small>
                                                <strong><?php echo date('d/m/Y', strtotime($seccion['fecha_inicio'])); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body py-2">
                                                <small class="text-muted d-block">Fecha Fin</small>
                                                <strong><?php echo date('d/m/Y', strtotime($seccion['fecha_fin'])); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($seccion['descripcion']): ?>
                                <div class="mb-3">
                                    <small class="text-muted d-block">Descripción Adicional</small>
                                    <p class="mb-0"><?php echo $seccion['descripcion']; ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Información del docente -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-chalkboard-teacher me-2"></i>Docente Asignado
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if ($seccion['docente_nombres']): ?>
                        <div class="text-center mb-3">
                            <div class="avatar-profile mb-2">
                                <div class="avatar-title bg-primary rounded-circle" style="width: 80px; height: 80px;">
                                    <i class="fas fa-user-tie fa-2x text-white"></i>
                                </div>
                            </div>
                            <h6 class="mb-1"><?php echo $seccion['docente_nombres'] . ' ' . $seccion['docente_apellidos']; ?></h6>
                            <small class="text-muted"><?php echo $seccion['docente_titulo']; ?></small>
                        </div>
                        
                        <div class="contact-info">
                            <small class="d-block mb-1">
                                <i class="fas fa-envelope me-2"></i><?php echo $seccion['docente_email']; ?>
                            </small>
                            <?php if ($seccion['docente_telefono']): ?>
                            <small class="d-block">
                                <i class="fas fa-phone me-2"></i><?php echo $seccion['docente_telefono']; ?>
                            </small>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-user-times fa-2x text-muted mb-3"></i>
                            <p class="text-muted mb-0">No hay docente asignado</p>
                            <button class="btn btn-sm btn-unexca mt-2" onclick="asignarDocente(<?php echo $seccion_id; ?>)">
                                <i class="fas fa-user-plus me-1"></i>Asignar Docente
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Estadísticas rápidas -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Estadísticas</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted d-block">Cupo</small>
                            <div class="d-flex align-items-center">
                                <div class="progress flex-grow-1 me-2" style="height: 10px;">
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: <?php echo $cupo_porcentaje; ?>%">
                                    </div>
                                </div>
                                <span><?php echo count($estudiantes); ?>/<?php echo $seccion['cupo_maximo']; ?></span>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <?php echo $cupo_porcentaje; ?>% de ocupación
                            </small>
                        </div>
                        
                        <?php if ($asistencia && $asistencia['total_clases'] > 0): ?>
                        <div class="mb-3">
                            <small class="text-muted d-block">Asistencia Promedio</small>
                            <?php
                            $asistencia_porcentaje = $asistencia['total_clases'] > 0 ? 
                                round(($asistencia['presentes'] / $asistencia['total_clases']) * 100, 1) : 0;
                            ?>
                            <div class="d-flex align-items-center">
                                <div class="progress flex-grow-1 me-2" style="height: 10px;">
                                    <div class="progress-bar bg-info" role="progressbar" 
                                         style="width: <?php echo $asistencia_porcentaje; ?>%">
                                    </div>
                                </div>
                                <span><?php echo $asistencia_porcentaje; ?>%</span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h5 mb-1"><?php echo $seccion['horas_teoria'] + $seccion['horas_practica']; ?></div>
                                <small class="text-muted">Horas/Semana</small>
                            </div>
                            <div class="col-6">
                                <div class="h5 mb-1"><?php echo $seccion['creditos']; ?></div>
                                <small class="text-muted">Créditos</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pestañas -->
        <ul class="nav nav-tabs mb-4" id="seccionTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="estudiantes-tab" data-bs-toggle="tab" 
                        data-bs-target="#estudiantes" type="button" role="tab">
                    <i class="fas fa-users me-2"></i>Estudiantes Matriculados
                    <span class="badge bg-primary ms-2"><?php echo count($estudiantes); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="calificaciones-tab" data-bs-toggle="tab" 
                        data-bs-target="#calificaciones" type="button" role="tab">
                    <i class="fas fa-graduation-cap me-2"></i>Calificaciones
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="asistencia-tab" data-bs-toggle="tab" 
                        data-bs-target="#asistencia" type="button" role="tab">
                    <i class="fas fa-clipboard-list me-2"></i>Asistencia
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="materiales-tab" data-bs-toggle="tab" 
                        data-bs-target="#materiales" type="button" role="tab">
                    <i class="fas fa-book me-2"></i>Materiales
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="seccionTabsContent">
            <!-- Estudiantes Matriculados -->
            <div class="tab-pane fade show active" id="estudiantes" role="tabpanel">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6>Lista de Estudiantes</h6>
                            <?php if ($seccion['estado'] == 'abierta'): ?>
                            <button class="btn btn-sm btn-unexca" onclick="matricularEstudiante()">
                                <i class="fas fa-user-plus me-1"></i>Matricular Estudiante
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Estudiante</th>
                                <th>Email</th>
                                <th>Semestre</th>
                                <th>Promedio</th>
                                <th>Estado Matrícula</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($estudiantes)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-users fa-2x text-muted mb-3"></i>
                                    <p class="text-muted">No hay estudiantes matriculados</p>
                                    <?php if ($seccion['estado'] == 'abierta'): ?>
                                    <button class="btn btn-unexca" onclick="matricularEstudiante()">
                                        <i class="fas fa-user-plus me-1"></i>Matricular Primer Estudiante
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($estudiantes as $estudiante): 
                                $estado_class = '';
                                switch ($estudiante['estado']) {
                                    case 'matriculado': $estado_class = 'info'; break;
                                    case 'aprobado': $estado_class = 'success'; break;
                                    case 'reprobado': $estado_class = 'danger'; break;
                                    case 'retirado': $estado_class = 'secondary'; break;
                                }
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo $estudiante['codigo_estudiante']; ?></strong>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-xs me-2">
                                            <div class="avatar-title bg-light rounded-circle">
                                                <i class="fas fa-user-graduate text-primary"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <small class="d-block"><?php echo $estudiante['nombres'] . ' ' . $estudiante['apellidos']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $estudiante['email']; ?></td>
                                <td><?php echo $estudiante['semestre_actual']; ?>° Semestre</td>
                                <td>
                                    <?php if ($estudiante['promedio_estudiante']): ?>
                                    <span class="badge bg-<?php echo ($estudiante['promedio_estudiante'] >= 16) ? 'success' : (($estudiante['promedio_estudiante'] >= 10) ? 'warning' : 'danger'); ?>">
                                        <?php echo number_format($estudiante['promedio_estudiante'], 2); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $estado_class; ?>">
                                        <?php echo ucfirst($estudiante['estado']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="estudiante_perfil.php?id=<?php echo $estudiante['estudiante_id']; ?>" 
                                           class="btn btn-outline-primary" title="Ver perfil">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($estudiante['estado'] == 'matriculado'): ?>
                                        <button class="btn btn-outline-danger" 
                                                onclick="retirarEstudiante(<?php echo $estudiante['id']; ?>, '<?php echo $estudiante['nombres'] . ' ' . $estudiante['apellidos']; ?>')"
                                                title="Retirar estudiante">
                                            <i class="fas fa-user-minus"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Resumen de estudiantes -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Distribución por Carrera</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="chartEstudiantesCarrera" height="80"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Calificaciones -->
            <div class="tab-pane fade" id="calificaciones" role="tabpanel">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6>Sistema de Calificaciones</h6>
                            <div>
                                <button class="btn btn-sm btn-outline-unexca me-2" onclick="exportarCalificaciones()">
                                    <i class="fas fa-file-export me-1"></i>Exportar
                                </button>
                                <button class="btn btn-sm btn-unexca" onclick="cargarCalificacionesMasivo()">
                                    <i class="fas fa-upload me-1"></i>Cargar Masivo
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (empty($tipos_evaluacion)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    No se han definido tipos de evaluación para esta sección.
                    <a href="secciones.php?action=editar&id=<?php echo $seccion_id; ?>" class="alert-link">
                        Configurar tipos de evaluación
                    </a>
                </div>
                <?php else: ?>
                
                <!-- Tabla de calificaciones -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th rowspan="2" style="vertical-align: middle;">Estudiante</th>
                                <?php foreach ($tipos_evaluacion as $tipo): ?>
                                <th colspan="2" class="text-center">
                                    <?php echo $tipo['nombre']; ?>
                                    <br>
                                    <small class="text-muted"><?php echo $tipo['peso']; ?>%</small>
                                </th>
                                <?php endforeach; ?>
                                <th rowspan="2" style="vertical-align: middle;">Nota Final</th>
                                <th rowspan="2" style="vertical-align: middle;">Estado</th>
                            </tr>
                            <tr>
                                <?php foreach ($tipos_evaluacion as $tipo): ?>
                                <th class="text-center">Nota</th>
                                <th class="text-center">Fecha</th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($estudiantes as $estudiante): 
                                // Calcular nota final
                                $nota_final = 0;
                                $todos_calificados = true;
                                
                                foreach ($tipos_evaluacion as $tipo) {
                                    $calificacion = $calificaciones[$estudiante['id']][$tipo['id']] ?? null;
                                    if ($calificacion && $calificacion['nota'] !== null) {
                                        $nota_final += ($calificacion['nota'] * $tipo['peso']) / 100;
                                    } else {
                                        $todos_calificados = false;
                                    }
                                }
                                
                                $estado_final = '';
                                $estado_class = '';
                                if ($todos_calificados) {
                                    if ($nota_final >= 10) {
                                        $estado_final = 'Aprobado';
                                        $estado_class = 'success';
                                    } else {
                                        $estado_final = 'Reprobado';
                                        $estado_class = 'danger';
                                    }
                                } else {
                                    $estado_final = 'En proceso';
                                    $estado_class = 'warning';
                                }
                            ?>
                            <tr>
                                <td>
                                    <small class="d-block"><?php echo $estudiante['nombres'] . ' ' . $estudiante['apellidos']; ?></small>
                                    <small class="text-muted"><?php echo $estudiante['codigo_estudiante']; ?></small>
                                </td>
                                
                                <?php foreach ($tipos_evaluacion as $tipo): 
                                    $calificacion = $calificaciones[$estudiante['id']][$tipo['id']] ?? null;
                                ?>
                                <td class="text-center">
                                    <?php if ($calificacion && $calificacion['nota'] !== null): ?>
                                    <span class="badge bg-<?php echo ($calificacion['nota'] >= 10) ? 'success' : 'danger'; ?>">
                                        <?php echo number_format($calificacion['nota'], 2); ?>
                                    </span>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary" 
                                            onclick="registrarCalificacion(<?php echo $estudiante['id']; ?>, <?php echo $tipo['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($calificacion && $calificacion['fecha_calificacion']): ?>
                                    <small><?php echo date('d/m/Y', strtotime($calificacion['fecha_calificacion'])); ?></small>
                                    <?php else: ?>
                                    <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                                
                                <td class="text-center">
                                    <?php if ($todos_calificados): ?>
                                    <span class="badge bg-<?php echo ($nota_final >= 10) ? 'success' : 'danger'; ?>">
                                        <?php echo number_format($nota_final, 2); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $estado_class; ?>">
                                        <?php echo $estado_final; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Distribución de notas -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Distribución de Calificaciones</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="chartDistribucionNotas" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Asistencia -->
            <div class="tab-pane fade" id="asistencia" role="tabpanel">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6>Control de Asistencia</h6>
                            <div>
                                <button class="btn btn-sm btn-outline-unexca me-2" onclick="exportarAsistencia()">
                                    <i class="fas fa-file-export me-1"></i>Exportar
                                </button>
                                <button class="btn btn-sm btn-unexca" onclick="tomarAsistencia()">
                                    <i class="fas fa-clipboard-check me-1"></i>Tomar Asistencia
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Calendario de asistencia -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Registro por Fecha</h6>
                    </div>
                    <div class="card-body">
                        <div id="calendarioAsistencia">
                            <!-- Se cargará dinámicamente -->
                        </div>
                    </div>
                </div>
                
                <!-- Resumen de asistencia -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Estadísticas de Asistencia</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 text-center">
                                        <div class="display-4 text-success"><?php echo $asistencia_porcentaje ?? 0; ?>%</div>
                                        <small class="text-muted">Asistencia Promedio</small>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <div class="display-4 text-primary"><?php echo $asistencia['presentes'] ?? 0; ?></div>
                                        <small class="text-muted">Clases con Asistencia</small>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <div class="display-4 text-warning"><?php echo $asistencia['total_clases'] ?? 0; ?></div>
                                        <small class="text-muted">Total de Clases</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Materiales -->
            <div class="tab-pane fade" id="materiales" role="tabpanel">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6>Materiales de la Sección</h6>
                            <button class="btn btn-sm btn-unexca" onclick="subirMaterial()">
                                <i class="fas fa-upload me-1"></i>Subir Material
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="row" id="listaMateriales">
                    <!-- Se cargará dinámicamente -->
                </div>
                
                <!-- Información del curso -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="mb-0">Información del Curso</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($seccion['curso_descripcion']): ?>
                        <h6>Descripción:</h6>
                        <p><?php echo $seccion['curso_descripcion']; ?></p>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Detalles:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-clock me-2"></i> Horas Teoría: <?php echo $seccion['horas_teoria']; ?></li>
                                    <li><i class="fas fa-flask me-2"></i> Horas Práctica: <?php echo $seccion['horas_practica']; ?></li>
                                    <li><i class="fas fa-book me-2"></i> Créditos: <?php echo $seccion['creditos']; ?></li>
                                    <li><i class="fas fa-graduation-cap me-2"></i> Semestre: <?php echo $seccion['semestre']; ?>°</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Requisitos:</h6>
                                <?php
                                $query = "SELECT prerequisito_id FROM cursos WHERE id = :curso_id";
                                $stmt = $conn->prepare($query);
                                $stmt->bindParam(':curso_id', $seccion['curso_id']);
                                $stmt->execute();
                                $prerequisito = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($prerequisito && $prerequisito['prerequisito_id']):
                                    $query = "SELECT codigo, nombre FROM cursos WHERE id = :prerequisito_id";
                                    $stmt = $conn->prepare($query);
                                    $stmt->bindParam(':prerequisito_id', $prerequisito['prerequisito_id']);
                                    $stmt->execute();
                                    $curso_prerequisito = $stmt->fetch(PDO::FETCH_ASSOC);
                                ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <strong>Prerrequisito:</strong> 
                                    <?php echo $curso_prerequisito['codigo'] . ' - ' . $curso_prerequisito['nombre']; ?>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    No tiene prerrequisitos
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para matricular estudiante -->
<div class="modal fade" id="modalMatricularEstudiante" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Matricular Estudiante</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formMatricularEstudiante">
                    <input type="hidden" name="seccion_id" value="<?php echo $seccion_id; ?>">
                    
                    <div class="mb-3">
                        <label for="buscar_estudiante" class="form-label">Buscar Estudiante</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="buscar_estudiante" 
                                   placeholder="Código, nombre o cédula del estudiante">
                            <button type="button" class="btn btn-outline-secondary" onclick="buscarEstudiante()">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div id="resultadosBusqueda" class="mb-3" style="display: none;">
                        <!-- Resultados de búsqueda -->
                    </div>
                    
                    <div class="mb-3">
                        <label for="estudiante_id" class="form-label">O seleccionar de la lista:</label>
                        <select class="form-select" id="estudiante_id" name="estudiante_id">
                            <option value="">Seleccionar estudiante...</option>
                            <?php
                            // Obtener estudiantes que pueden matricularse (misma carrera, no matriculados ya)
                            $query = "SELECT e.* 
                                      FROM estudiantes e 
                                      WHERE e.carrera_id = :carrera_id 
                                      AND e.estado = 'activo'
                                      AND e.id NOT IN (
                                          SELECT m.estudiante_id 
                                          FROM matriculas m 
                                          WHERE m.seccion_id = :seccion_id
                                      )
                                      ORDER BY e.apellidos, e.nombres";
                            $stmt = $conn->prepare($query);
                            $stmt->execute([
                                ':carrera_id' => $seccion['carrera_id'],
                                ':seccion_id' => $seccion_id
                            ]);
                            $estudiantes_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($estudiantes_disponibles as $est):
                            ?>
                            <option value="<?php echo $est['id']; ?>">
                                <?php echo $est['codigo_estudiante'] . ' - ' . $est['nombres'] . ' ' . $est['apellidos']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Solo se muestran estudiantes activos de la carrera <?php echo $seccion['carrera_nombre']; ?>
                        que no están ya matriculados en esta sección.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-unexca" onclick="matricularEstudianteConfirmar()">
                    <i class="fas fa-user-plus me-2"></i>Matricular
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function asignarDocente(seccionId) {
    // Esta función se implementaría similar a la del archivo principal
    alert('Funcionalidad en desarrollo');
}

function matricularEstudiante() {
    const modal = new bootstrap.Modal(document.getElementById('modalMatricularEstudiante'));
    modal.show();
}

function buscarEstudiante() {
    const termino = document.getElementById('buscar_estudiante').value;
    const resultados = document.getElementById('resultadosBusqueda');
    
    if (!termino) {
        resultados.style.display = 'none';
        return;
    }
    
    fetch(`ajax/buscar_estudiante.php?q=${encodeURIComponent(termino)}&seccion_id=<?php echo $seccion_id; ?>`)
    .then(response => response.json())
    .then(data => {
        resultados.innerHTML = '';
        
        if (data.length === 0) {
            resultados.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    No se encontraron estudiantes
                </div>
            `;
            resultados.style.display = 'block';
            return;
        }
        
        const lista = document.createElement('div');
        lista.className = 'list-group';
        
        data.forEach(estudiante => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action';
            item.innerHTML = `
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1">${estudiante.nombres} ${estudiante.apellidos}</h6>
                    <small>${estudiante.codigo_estudiante}</small>
                </div>
                <p class="mb-1">${estudiante.email}</p>
                <small>Carrera: ${estudiante.carrera_nombre}</small>
            `;
            item.onclick = () => {
                document.getElementById('estudiante_id').value = estudiante.id;
                resultados.style.display = 'none';
            };
            lista.appendChild(item);
        });
        
        resultados.appendChild(lista);
        resultados.style.display = 'block';
    });
}

function matricularEstudianteConfirmar() {
    const estudianteId = document.getElementById('estudiante_id').value;
    
    if (!estudianteId) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Por favor seleccione un estudiante'
        });
        return;
    }
    
    const formData = new FormData(document.getElementById('formMatricularEstudiante'));
    
    UNEXCA.Utils.showLoading('#modalMatricularEstudiante .modal-content', 'Matriculando estudiante...');
    
    fetch('ajax/matricular_estudiante.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        UNEXCA.Utils.hideLoading('#modalMatricularEstudiante .modal-content');
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Estudiante matriculado!',
                text: data.message,
                confirmButtonText: 'Continuar'
            }).then(() => {
                bootstrap.Modal.getInstance(document.getElementById('modalMatricularEstudiante')).hide();
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message
            });
        }
    });
}

function retirarEstudiante(matriculaId, nombreEstudiante) {
    Swal.fire({
        title: 'Retirar Estudiante',
        html: `¿Está seguro de retirar a <strong>${nombreEstudiante}</strong> de esta sección?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, retirar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`ajax/retirar_estudiante.php?id=${matriculaId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Estudiante retirado!',
                        text: data.message,
                        confirmButtonText: 'Continuar'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message
                    });
                }
            });
        }
    });
}

function registrarCalificacion(estudianteId, tipoId) {
    Swal.fire({
        title: 'Registrar Calificación',
        html: `
            <form id="formCalificacion">
                <input type="hidden" name="estudiante_id" value="${estudianteId}">
                <input type="hidden" name="tipo_evaluacion_id" value="${tipoId}">
                <input type="hidden" name="seccion_id" value="<?php echo $seccion_id; ?>">
                
                <div class="mb-3">
                    <label for="nota" class="form-label">Nota (0-20)</label>
                    <input type="number" class="form-control" id="nota" name="nota" 
                           min="0" max="20" step="0.01" required>
                </div>
                
                <div class="mb-3">
                    <label for="observaciones" class="form-label">Observaciones</label>
                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const form = document.getElementById('formCalificacion');
            const formData = new FormData(form);
            
            return fetch('ajax/registrar_calificacion.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message);
                }
                return data;
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: '¡Calificación registrada!',
                text: 'La calificación ha sido registrada exitosamente',
                confirmButtonText: 'Continuar'
            }).then(() => {
                location.reload();
            });
        }
    });
}

function exportarCalificaciones() {
    UNEXCA.Utils.showNotification('Generando archivo de calificaciones...', 'info');
    
    setTimeout(() => {
        window.open(`ajax/exportar_calificaciones.php?seccion_id=<?php echo $seccion_id; ?>`, '_blank');
        UNEXCA.Utils.showNotification('Archivo generado exitosamente', 'success');
    }, 1000);
}

function cargarCalificacionesMasivo() {
    Swal.fire({
        title: 'Cargar Calificaciones Masivo',
        html: `
            <form id="formCalificacionesMasivo" enctype="multipart/form-data">
                <input type="hidden" name="seccion_id" value="<?php echo $seccion_id; ?>">
                
                <div class="mb-3">
                    <label for="archivo_calificaciones" class="form-label">Archivo CSV</label>
                    <input type="file" class="form-control" id="archivo_calificaciones" 
                           name="archivo_calificaciones" accept=".csv" required>
                    <small class="text-muted">
                        <a href="plantilla_calificaciones.csv" class="d-block mt-1">
                            <i class="fas fa-download me-1"></i>Descargar plantilla
                        </a>
                        Formato: codigo_estudiante,tipo_evaluacion,nota,observaciones
                    </small>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Cargar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.getElementById('formCalificacionesMasivo');
            const formData = new FormData(form);
            
            UNEXCA.Utils.showLoading('body', 'Procesando archivo...');
            
            fetch('ajax/cargar_calificaciones_masivo.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                UNEXCA.Utils.hideLoading('body');
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Archivo procesado!',
                        html: `Se procesaron ${data.procesados} calificaciones<br>
                               ${data.errores} registros tuvieron errores`,
                        confirmButtonText: 'Continuar'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message
                    });
                }
            });
        }
    });
}

function tomarAsistencia() {
    const fecha = new Date().toISOString().split('T')[0];
    
    Swal.fire({
        title: 'Tomar Asistencia',
        html: `
            <form id="formAsistencia">
                <input type="hidden" name="seccion_id" value="<?php echo $seccion_id; ?>">
                
                <div class="mb-3">
                    <label for="fecha_asistencia" class="form-label">Fecha</label>
                    <input type="date" class="form-control" id="fecha_asistencia" 
                           name="fecha" value="${fecha}" required>
                </div>
                
                <div class="mb-3">
                    <label for="tema_clase" class="form-label">Tema de la Clase</label>
                    <input type="text" class="form-control" id="tema_clase" name="tema_clase">
                </div>
                
                <div id="listaAsistenciaEstudiantes">
                    <!-- Se cargará dinámicamente -->
                </div>
            </form>
        `,
        width: '800px',
        showCancelButton: true,
        confirmButtonText: 'Guardar Asistencia',
        cancelButtonText: 'Cancelar',
        didOpen: () => {
            // Cargar lista de estudiantes
            fetch(`ajax/obtener_estudiantes_asistencia.php?seccion_id=<?php echo $seccion_id; ?>`)
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('listaAsistenciaEstudiantes');
                container.innerHTML = '';
                
                data.forEach(estudiante => {
                    const div = document.createElement('div');
                    div.className = 'mb-2';
                    div.innerHTML = `
                        <div class="d-flex align-items-center justify-content-between">
                            <span>${estudiante.nombres} ${estudiante.apellidos}</span>
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check" name="estado_${estudiante.id}" 
                                       id="presente_${estudiante.id}" value="presente" checked>
                                <label class="btn btn-outline-success" for="presente_${estudiante.id}">Presente</label>
                                
                                <input type="radio" class="btn-check" name="estado_${estudiante.id}" 
                                       id="ausente_${estudiante.id}" value="ausente">
                                <label class="btn btn-outline-danger" for="ausente_${estudiante.id}">Ausente</label>
                                
                                <input type="radio" class="btn-check" name="estado_${estudiante.id}" 
                                       id="justificado_${estudiante.id}" value="justificado">
                                <label class="btn btn-outline-warning" for="justificado_${estudiante.id}">Justificado</label>
                            </div>
                        </div>
                    `;
                    container.appendChild(div);
                });
            });
        },
        preConfirm: () => {
            const form = document.getElementById('formAsistencia');
            const formData = new FormData(form);
            
            // Agregar estados de asistencia
            <?php foreach ($estudiantes as $estudiante): ?>
            const estado<?php echo $estudiante['id']; ?> = document.querySelector(`input[name="estado_<?php echo $estudiante['id']; ?>"]:checked`);
            if (estado<?php echo $estudiante['id']; ?>) {
                formData.append('estudiantes[<?php echo $estudiante['id']; ?>]', estado<?php echo $estudiante['id']; ?>.value);
            }
            <?php endforeach; ?>
            
            return fetch('ajax/registrar_asistencia.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message);
                }
                return data;
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: '¡Asistencia registrada!',
                text: 'La asistencia ha sido registrada exitosamente',
                confirmButtonText: 'Continuar'
            }).then(() => {
                location.reload();
            });
        }
    });
}

function exportarAsistencia() {
    UNEXCA.Utils.showNotification('Generando reporte de asistencia...', 'info');
    
    setTimeout(() => {
        window.open(`ajax/exportar_asistencia.php?seccion_id=<?php echo $seccion_id; ?>`, '_blank');
        UNEXCA.Utils.showNotification('Reporte generado exitosamente', 'success');
    }, 1000);
}

function subirMaterial() {
    Swal.fire({
        title: 'Subir Material',
        html: `
            <form id="formSubirMaterial" enctype="multipart/form-data">
                <input type="hidden" name="seccion_id" value="<?php echo $seccion_id; ?>">
                
                <div class="mb-3">
                    <label for="tipo_material" class="form-label">Tipo de Material</label>
                    <select class="form-select" id="tipo_material" name="tipo" required>
                        <option value="">Seleccionar tipo</option>
                        <option value="syllabus">Syllabus</option>
                        <option value="presentacion">Presentación</option>
                        <option value="tarea">Tarea</option>
                        <option value="lectura">Lectura</option>
                        <option value="examen">Examen</option>
                        <option value="otros">Otros</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="titulo_material" class="form-label">Título</label>
                    <input type="text" class="form-control" id="titulo_material" name="titulo" required>
                </div>
                
                <div class="mb-3">
                    <label for="descripcion_material" class="form-label">Descripción</label>
                    <textarea class="form-control" id="descripcion_material" name="descripcion" rows="2"></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="archivo_material" class="form-label">Archivo</label>
                    <input type="file" class="form-control" id="archivo_material" name="archivo" 
                           accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.zip" required>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Subir',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const form = document.getElementById('formSubirMaterial');
            const formData = new FormData(form);
            
            return fetch('ajax/subir_material.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message);
                }
                return data;
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: '¡Material subido!',
                text: 'El material ha sido subido exitosamente',
                confirmButtonText: 'Continuar'
            }).then(() => {
                cargarMateriales();
            });
        }
    });
}

function cargarMateriales() {
    fetch(`ajax/obtener_materiales.php?seccion_id=<?php echo $seccion_id; ?>`)
    .then(response => response.json())
    .then(data => {
        const container = document.getElementById('listaMateriales');
        container.innerHTML = '';
        
        if (data.length === 0) {
            container.innerHTML = `
                <div class="col-md-12 text-center py-5">
                    <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No hay materiales subidos</h5>
                    <p class="text-muted">Sube el primer material para esta sección</p>
                </div>
            `;
            return;
        }
        
        data.forEach(material => {
            const col = document.createElement('div');
            col.className = 'col-md-4 mb-3';
            
            const icono = obtenerIconoMaterial(material.tipo);
            const fecha = new Date(material.fecha_subida).toLocaleDateString('es-ES');
            
            col.innerHTML = `
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start mb-3">
                            <div class="avatar-sm me-3">
                                <div class="avatar-title bg-light rounded-circle">
                                    <i class="fas fa-${icono} text-primary"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${material.titulo}</h6>
                                <small class="text-muted">${material.tipo} • ${fecha}</small>
                            </div>
                        </div>
                        <p class="card-text small">${material.descripcion || 'Sin descripción'}</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">${material.tamano}</small>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="descargarMaterial('${material.archivo}')">
                                    <i class="fas fa-download"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="eliminarMaterial(${material.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(col);
        });
    });
}

function obtenerIconoMaterial(tipo) {
    switch(tipo) {
        case 'syllabus': return 'file-contract';
        case 'presentacion': return 'file-powerpoint';
        case 'tarea': return 'file-alt';
        case 'lectura': return 'book';
        case 'examen': return 'file-signature';
        default: return 'file';
    }
}

function descargarMaterial(archivo) {
    window.open(`ajax/descargar_material.php?archivo=${encodeURIComponent(archivo)}`, '_blank');
}

function eliminarMaterial(materialId) {
    Swal.fire({
        title: 'Eliminar Material',
        text: '¿Está seguro de eliminar este material?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`ajax/eliminar_material.php?id=${materialId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Material eliminado!',
                        text: 'El material ha sido eliminado exitosamente',
                        confirmButtonText: 'Continuar'
                    }).then(() => {
                        cargarMateriales();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message
                    });
                }
            });
        }
    });
}

// Generar gráficos
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de distribución por carrera (estudiantes)
    <?php if (!empty($estudiantes)): ?>
    const ctxCarrera = document.getElementById('chartEstudiantesCarrera').getContext('2d');
    
    <?php
    // Preparar datos de carrera de estudiantes
    $carreras_estudiantes = [];
    foreach ($estudiantes as $estudiante) {
        $carrera = $estudiante['carrera_nombre'] ?? 'Sin carrera';
        $carreras_estudiantes[$carrera] = ($carreras_estudiantes[$carrera] ?? 0) + 1;
    }
    ?>
    
    new Chart(ctxCarrera, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_keys($carreras_estudiantes)); ?>,
            datasets: [{
                label: 'Estudiantes',
                data: <?php echo json_encode(array_values($carreras_estudiantes)); ?>,
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
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    // Gráfico de distribución de notas (si hay calificaciones)
    <?php 
    $distribucion_notas = [
        '0-4' => 0, '5-9' => 0, '10-14' => 0, '15-20' => 0
    ];
    
    if (!empty($estudiantes) && !empty($tipos_evaluacion)) {
        foreach ($estudiantes as $estudiante) {
            $nota_final = 0;
            $todos_calificados = true;
            
            foreach ($tipos_evaluacion as $tipo) {
                $calificacion = $calificaciones[$estudiante['id']][$tipo['id']] ?? null;
                if ($calificacion && $calificacion['nota'] !== null) {
                    $nota_final += ($calificacion['nota'] * $tipo['peso']) / 100;
                } else {
                    $todos_calificados = false;
                    break;
                }
            }
            
            if ($todos_calificados) {
                if ($nota_final <= 4) $distribucion_notas['0-4']++;
                elseif ($nota_final <= 9) $distribucion_notas['5-9']++;
                elseif ($nota_final <= 14) $distribucion_notas['10-14']++;
                else $distribucion_notas['15-20']++;
            }
        }
    }
    ?>
    
    <?php if (array_sum($distribucion_notas) > 0): ?>
    const ctxNotas = document.getElementById('chartDistribucionNotas').getContext('2d');
    
    new Chart(ctxNotas, {
        type: 'pie',
        data: {
            labels: ['0-4', '5-9', '10-14', '15-20'],
            datasets: [{
                data: <?php echo json_encode(array_values($distribucion_notas)); ?>,
                backgroundColor: ['#dc3545', '#ffc107', '#28a745', '#0056b3'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right'
                },
                title: {
                    display: true,
                    text: 'Distribución de Notas Finales'
                }
            }
        }
    });
    <?php endif; ?>
    
    // Cargar materiales cuando se abra la pestaña
    const materialesTab = document.getElementById('materiales-tab');
    if (materialesTab) {
        materialesTab.addEventListener('shown.bs.tab', function() {
            cargarMateriales();
        });
    }
    
    // Cargar calendario de asistencia
    const asistenciaTab = document.getElementById('asistencia-tab');
    if (asistenciaTab) {
        asistenciaTab.addEventListener('shown.bs.tab', function() {
            cargarCalendarioAsistencia();
        });
    }
});

function cargarCalendarioAsistencia() {
    fetch(`ajax/obtener_calendario_asistencia.php?seccion_id=<?php echo $seccion_id; ?>`)
    .then(response => response.json())
    .then(data => {
        const container = document.getElementById('calendarioAsistencia');
        container.innerHTML = '';
        
        if (data.length === 0) {
            container.innerHTML = `
                <div class="text-center py-3">
                    <i class="fas fa-calendar fa-2x text-muted mb-3"></i>
                    <p class="text-muted">No hay registros de asistencia</p>
                </div>
            `;
            return;
        }
        
        const table = document.createElement('table');
        table.className = 'table table-hover table-sm';
        table.innerHTML = `
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tema</th>
                    <th>Presentes</th>
                    <th>Ausentes</th>
                    <th>Justificados</th>
                    <th>% Asistencia</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        `;
        
        const tbody = table.querySelector('tbody');
        
        data.forEach(registro => {
            const porcentaje = registro.total > 0 ? 
                Math.round((registro.presentes / registro.total) * 100) : 0;
            
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${registro.fecha}</td>
                <td>${registro.tema || '-'}</td>
                <td><span class="badge bg-success">${registro.presentes}</span></td>
                <td><span class="badge bg-danger">${registro.ausentes}</span></td>
                <td><span class="badge bg-warning">${registro.justificados}</span></td>
                <td>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-info" role="progressbar" 
                             style="width: ${porcentaje}%"></div>
                    </div>
                    <small>${porcentaje}%</small>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="verDetalleAsistencia('${registro.fecha}')">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
        
        container.appendChild(table);
    });
}

function verDetalleAsistencia(fecha) {
    fetch(`ajax/obtener_detalle_asistencia.php?seccion_id=<?php echo $seccion_id; ?>&fecha=${fecha}`)
    .then(response => response.json())
    .then(data => {
        let contenido = `
            <h6>Asistencia del ${fecha}</h6>
            <div class="table-responsive mt-3">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Estado</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        data.forEach(item => {
            let estadoBadge = '';
            switch(item.estado) {
                case 'presente':
                    estadoBadge = '<span class="badge bg-success">Presente</span>';
                    break;
                case 'ausente':
                    estadoBadge = '<span class="badge bg-danger">Ausente</span>';
                    break;
                case 'justificado':
                    estadoBadge = '<span class="badge bg-warning">Justificado</span>';
                    break;
            }
            
            contenido += `
                <tr>
                    <td>${item.nombres} ${item.apellidos}</td>
                    <td>${estadoBadge}</td>
                    <td>${item.observaciones || '-'}</td>
                </tr>
            `;
        });
        
        contenido += `
                    </tbody>
                </table>
            </div>
        `;
        
        Swal.fire({
            title: 'Detalle de Asistencia',
            html: contenido,
            width: '800px',
            confirmButtonText: 'Cerrar'
        });
    });
}
</script>

<style>
.avatar-profile {
    display: flex;
    justify-content: center;
    align-items: center;
}

.avatar-xs {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
}

.nav-tabs .nav-link {
    color: #6c757d;
    font-weight: 500;
}

.nav-tabs .nav-link.active {
    color: #0056b3;
    border-bottom: 3px solid #0056b3;
}

.tab-content {
    padding-top: 20px;
}

.display-4 {
    font-size: 3.5rem;
    font-weight: 300;
    line-height: 1.2;
}

.progress {
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    border-radius: 10px;
}

.table-dark th {
    background-color: #343a40;
    color: white;
}
</style>

<?php include '../../includes/footer.php'; ?>