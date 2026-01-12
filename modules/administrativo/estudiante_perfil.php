<?php
// modules/administrativo/estudiante_perfil.php
require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SESSION['rol'] != 'administrador') {
    header('Location: ../../index.php');
    exit();
}

$page_title = 'Perfil del Estudiante';

$db = new Database();
$conn = $db->getConnection();

$estudiante_id = $_GET['id'] ?? 0;

if (!$estudiante_id) {
    header('Location: estudiantes.php');
    exit();
}

// Obtener información del estudiante
$query = "SELECT e.*, 
                 c.nombre as carrera_nombre,
                 c.codigo as carrera_codigo,
                 c.duracion_semestres,
                 u.username,
                 u.estado as usuario_estado
          FROM estudiantes e 
          JOIN carreras c ON e.carrera_id = c.id 
          LEFT JOIN usuarios u ON e.usuario_id = u.id 
          WHERE e.id = :id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $estudiante_id);
$stmt->execute();
$estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$estudiante) {
    $_SESSION['message'] = 'Estudiante no encontrado';
    $_SESSION['message_type'] = 'danger';
    header('Location: estudiantes.php');
    exit();
}

// Calcular edad
$edad = $estudiante['fecha_nacimiento'] ? 
    date_diff(date_create($estudiante['fecha_nacimiento']), date_create('today'))->y : null;

// Obtener estadísticas académicas
$query = "SELECT 
            COUNT(DISTINCT m.id) as total_cursos,
            COUNT(DISTINCT CASE WHEN m.nota_final >= 10 THEN m.id END) as cursos_aprobados,
            COUNT(DISTINCT CASE WHEN m.nota_final >= 16 THEN m.id END) as cursos_excelencia,
            AVG(m.nota_final) as promedio_general,
            MIN(m.nota_final) as nota_minima,
            MAX(m.nota_final) as nota_maxima,
            SUM(c.creditos) as creditos_aprobados,
            (SELECT SUM(c2.creditos) 
             FROM cursos c2 
             JOIN carreras ca ON c2.carrera_id = ca.id 
             WHERE ca.id = e.carrera_id 
             AND c2.semestre <= e.semestre_actual) as creditos_plan
          FROM estudiantes e 
          LEFT JOIN matriculas m ON e.id = m.estudiante_id 
          LEFT JOIN secciones s ON m.seccion_id = s.id 
          LEFT JOIN cursos c ON s.curso_id = c.id 
          WHERE e.id = :id 
          GROUP BY e.id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $estudiante_id);
$stmt->execute();
$estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener historial académico
$query = "SELECT 
            s.periodo_academico,
            s.codigo_seccion,
            c.codigo as curso_codigo,
            c.nombre as curso_nombre,
            c.creditos,
            m.nota_final,
            m.estado as estado_matricula,
            d.nombres as docente_nombres,
            d.apellidos as docente_apellidos
          FROM matriculas m 
          JOIN secciones s ON m.seccion_id = s.id 
          JOIN cursos c ON s.curso_id = c.id 
          LEFT JOIN docentes d ON s.docente_id = d.id 
          WHERE m.estudiante_id = :id 
          ORDER BY s.periodo_academico DESC, c.nombre";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $estudiante_id);
$stmt->execute();
$historial = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener pagos del estudiante
$query = "SELECT * FROM pagos 
          WHERE estudiante_id = :id 
          ORDER BY fecha_vencimiento DESC 
          LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $estudiante_id);
$stmt->execute();
$pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener próximos cursos disponibles
$query = "SELECT c.*, ca.nombre as carrera_nombre
          FROM cursos c 
          JOIN carreras ca ON c.carrera_id = ca.id 
          WHERE ca.id = :carrera_id 
          AND c.semestre = :semestre_actual 
          AND c.estado = 'activo'
          AND NOT EXISTS (
              SELECT 1 FROM matriculas m 
              JOIN secciones s ON m.seccion_id = s.id 
              WHERE s.curso_id = c.id 
              AND m.estudiante_id = :estudiante_id
          )";
$stmt = $conn->prepare($query);
$stmt->bindParam(':carrera_id', $estudiante['carrera_id']);
$stmt->bindParam(':semestre_actual', $estudiante['semestre_actual']);
$stmt->bindParam(':estudiante_id', $estudiante_id);
$stmt->execute();
$proximos_cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        <h5 class="mb-0">Perfil del Estudiante</h5>
        <div>
            <a href="estudiantes.php?action=editar&id=<?php echo $estudiante_id; ?>" class="btn btn-unexca me-2">
                <i class="fas fa-edit me-2"></i>Editar
            </a>
            <a href="estudiantes.php" class="btn btn-outline-secondary">
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
                            <div class="col-md-3 text-center">
                                <div class="avatar-profile mb-3">
                                    <div class="avatar-title bg-primary rounded-circle" style="width: 120px; height: 120px;">
                                        <i class="fas fa-user-graduate fa-3x text-white"></i>
                                    </div>
                                </div>
                                <h5><?php echo $estudiante['nombres'] . ' ' . $estudiante['apellidos']; ?></h5>
                                <span class="badge bg-<?php echo ($estudiante['estado'] == 'activo') ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($estudiante['estado']); ?>
                                </span>
                            </div>
                            <div class="col-md-9">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <small class="text-muted d-block">Código</small>
                                        <strong><?php echo $estudiante['codigo_estudiante']; ?></strong>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <small class="text-muted d-block">Cédula</small>
                                        <strong><?php echo $estudiante['cedula']; ?></strong>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <small class="text-muted d-block">Carrera</small>
                                        <strong><?php echo $estudiante['carrera_nombre']; ?></strong>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <small class="text-muted d-block">Semestre Actual</small>
                                        <strong><?php echo $estudiante['semestre_actual']; ?>° Semestre</strong>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <small class="text-muted d-block">Email</small>
                                        <strong><?php echo $estudiante['email']; ?></strong>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <small class="text-muted d-block">Teléfono</small>
                                        <strong><?php echo $estudiante['telefono'] ?: 'No registrado'; ?></strong>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <small class="text-muted d-block">Fecha de Nacimiento</small>
                                        <strong><?php echo date('d/m/Y', strtotime($estudiante['fecha_nacimiento'])); ?></strong>
                                        <?php if ($edad): ?>
                                        <small class="text-muted">(<?php echo $edad; ?> años)</small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <small class="text-muted d-block">Fecha de Ingreso</small>
                                        <strong><?php echo date('d/m/Y', strtotime($estudiante['fecha_ingreso'])); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Estadísticas rápidas -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Rendimiento Académico</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <div class="display-4 fw-bold text-primary">
                                <?php echo $estadisticas['promedio_general'] ? number_format($estadisticas['promedio_general'], 2) : 'N/A'; ?>
                            </div>
                            <small class="text-muted">Promedio General</small>
                        </div>
                        
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h5 mb-1"><?php echo $estadisticas['cursos_aprobados'] ?? 0; ?></div>
                                <small class="text-muted">Cursos Aprobados</small>
                            </div>
                            <div class="col-6">
                                <div class="h5 mb-1"><?php echo $estadisticas['creditos_aprobados'] ?? 0; ?></div>
                                <small class="text-muted">Créditos</small>
                            </div>
                        </div>
                        
                        <div class="progress mt-3" style="height: 10px;">
                            <?php
                            $porcentaje_aprobacion = $estadisticas['total_cursos'] > 0 ? 
                                ($estadisticas['cursos_aprobados'] / $estadisticas['total_cursos']) * 100 : 0;
                            ?>
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php echo $porcentaje_aprobacion; ?>%">
                            </div>
                        </div>
                        <small class="text-muted d-block mt-1">
                            <?php echo number_format($porcentaje_aprobacion, 1); ?>% de aprobación
                        </small>
                    </div>
                </div>
                
                <!-- Acciones rápidas -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Acciones Rápidas</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" onclick="enviarMensaje()">
                                <i class="fas fa-envelope me-2"></i>Enviar Mensaje
                            </button>
                            <button class="btn btn-outline-success" onclick="generarCertificado()">
                                <i class="fas fa-file-certificate me-2"></i>Certificado de Estudios
                            </button>
                            <button class="btn btn-outline-warning" onclick="registrarPago()">
                                <i class="fas fa-money-bill-wave me-2"></i>Registrar Pago
                            </button>
                            <button class="btn btn-outline-info" onclick="verHistorialCompleto()">
                                <i class="fas fa-history me-2"></i>Historial Completo
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pestañas de información -->
        <ul class="nav nav-tabs mb-4" id="perfilTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="historial-tab" data-bs-toggle="tab" 
                        data-bs-target="#historial" type="button" role="tab">
                    <i class="fas fa-history me-2"></i>Historial Académico
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pagos-tab" data-bs-toggle="tab" 
                        data-bs-target="#pagos" type="button" role="tab">
                    <i class="fas fa-money-bill-wave me-2"></i>Historial de Pagos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="cursos-tab" data-bs-toggle="tab" 
                        data-bs-target="#cursos" type="button" role="tab">
                    <i class="fas fa-book me-2"></i>Próximos Cursos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="documentos-tab" data-bs-toggle="tab" 
                        data-bs-target="#documentos" type="button" role="tab">
                    <i class="fas fa-folder me-2"></i>Documentos
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="perfilTabsContent">
            <!-- Historial Académico -->
            <div class="tab-pane fade show active" id="historial" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Período</th>
                                <th>Curso</th>
                                <th>Sección</th>
                                <th>Créditos</th>
                                <th>Nota Final</th>
                                <th>Docente</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($historial)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-book fa-2x text-muted mb-3"></i>
                                    <p class="text-muted">No hay historial académico registrado</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($historial as $curso): ?>
                            <tr>
                                <td><?php echo $curso['periodo_academico']; ?></td>
                                <td>
                                    <strong><?php echo $curso['curso_nombre']; ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo $curso['curso_codigo']; ?></small>
                                </td>
                                <td><?php echo $curso['codigo_seccion']; ?></td>
                                <td><?php echo $curso['creditos']; ?></td>
                                <td>
                                    <?php if ($curso['nota_final'] !== null): ?>
                                    <span class="badge bg-<?php echo ($curso['nota_final'] >= 10) ? 'success' : 'danger'; ?>">
                                        <?php echo number_format($curso['nota_final'], 2); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">En proceso</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($curso['docente_nombres']): ?>
                                    <?php echo $curso['docente_nombres'] . ' ' . $curso['docente_apellidos']; ?>
                                    <?php else: ?>
                                    <span class="text-muted">Sin asignar</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo ($curso['estado_matricula'] == 'aprobado') ? 'success' : 'info'; ?>">
                                        <?php echo ucfirst($curso['estado_matricula']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Gráfico de rendimiento por período -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="mb-0">Evolución del Rendimiento</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="chartRendimientoPeriodo" height="100"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Historial de Pagos -->
            <div class="tab-pane fade" id="pagos" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Fecha Vencimiento</th>
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
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-money-bill-wave fa-2x text-muted mb-3"></i>
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
                                    default:
                                        $estado_class = 'secondary';
                                        $estado_text = ucfirst($pago['estado']);
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
                                <td><?php echo $pago['concepto']; ?></td>
                                <td><strong>$<?php echo number_format($pago['monto'], 2); ?></strong></td>
                                <td><?php echo ucfirst($pago['metodo_pago']); ?></td>
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
                                    <?php if ($pago['estado'] == 'pendiente'): ?>
                                    <button class="btn btn-sm btn-outline-success" 
                                            onclick="marcarPagoComoPagado(<?php echo $pago['id']; ?>)">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="text-center mt-3">
                    <a href="finanzas.php?estudiante_id=<?php echo $estudiante_id; ?>" class="btn btn-unexca">
                        <i class="fas fa-plus me-2"></i>Registrar Nuevo Pago
                    </a>
                </div>
            </div>
            
            <!-- Próximos Cursos -->
            <div class="tab-pane fade" id="cursos" role="tabpanel">
                <div class="row">
                    <?php if (empty($proximos_cursos)): ?>
                    <div class="col-md-12 text-center py-5">
                        <i class="fas fa-book fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No hay cursos disponibles</h5>
                        <p class="text-muted">El estudiante ya ha matriculado todos los cursos de este semestre</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($proximos_cursos as $curso): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title"><?php echo $curso['nombre']; ?></h6>
                                <p class="card-text text-muted small">
                                    Código: <?php echo $curso['codigo']; ?><br>
                                    Créditos: <?php echo $curso['creditos']; ?><br>
                                    Semestre: <?php echo $curso['semestre']; ?>
                                </p>
                                <?php if ($curso['descripcion']): ?>
                                <p class="card-text"><?php echo substr($curso['descripcion'], 0, 100); ?>...</p>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between mt-3">
                                    <button class="btn btn-sm btn-outline-info" 
                                            onclick="verDetallesCurso(<?php echo $curso['id']; ?>)">
                                        <i class="fas fa-info-circle me-1"></i>Detalles
                                    </button>
                                    <button class="btn btn-sm btn-unexca" 
                                            onclick="matricularEnCurso(<?php echo $curso['id']; ?>)">
                                        <i class="fas fa-plus me-1"></i>Matricular
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Prerrequisitos pendientes -->
                <?php
                // Obtener cursos con prerrequisitos no cumplidos
                $query = "SELECT c.*, 
                                 (SELECT nombre FROM cursos WHERE id = c.prerequisito_id) as prerequisito_nombre
                          FROM cursos c 
                          WHERE c.carrera_id = :carrera_id 
                          AND c.semestre <= :semestre_actual 
                          AND c.prerequisito_id IS NOT NULL
                          AND NOT EXISTS (
                              SELECT 1 FROM matriculas m 
                              JOIN secciones s ON m.seccion_id = s.id 
                              WHERE s.curso_id = c.prerequisito_id 
                              AND m.estudiante_id = :estudiante_id
                              AND m.nota_final >= 10
                          )";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':carrera_id', $estudiante['carrera_id']);
                $stmt->bindParam(':semestre_actual', $estudiante['semestre_actual']);
                $stmt->bindParam(':estudiante_id', $estudiante_id);
                $stmt->execute();
                $prerrequisitos_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($prerrequisitos_pendientes)):
                ?>
                <div class="card mt-4">
                    <div class="card-header bg-warning text-white">
                        <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Prerrequisitos Pendientes</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <p>Los siguientes cursos tienen prerrequisitos que el estudiante no ha aprobado:</p>
                        </div>
                        <ul class="list-group">
                            <?php foreach ($prerrequisitos_pendientes as $curso): ?>
                            <li class="list-group-item">
                                <strong><?php echo $curso['nombre']; ?></strong> (<?php echo $curso['codigo']; ?>)
                                <br>
                                <small class="text-muted">
                                    Prerrequisito: <?php echo $curso['prerequisito_nombre']; ?>
                                </small>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Documentos -->
            <div class="tab-pane fade" id="documentos" role="tabpanel">
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Documentos del Estudiante</h6>
                                <button class="btn btn-sm btn-unexca" onclick="subirDocumento()">
                                    <i class="fas fa-upload me-1"></i>Subir Documento
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Tipo</th>
                                                <th>Nombre</th>
                                                <th>Tamaño</th>
                                                <th>Fecha Subida</th>
                                                <th>Estado</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="listaDocumentos">
                                            <!-- Los documentos se cargarán dinámicamente -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Documentos requeridos -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="mb-0">Documentos Requeridos</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="doc_foto" checked disabled>
                                    <label class="form-check-label" for="doc_foto">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        Fotografía 3x4
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="doc_cedula" checked disabled>
                                    <label class="form-check-label" for="doc_cedula">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        Copia de Cédula
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="doc_partida" checked disabled>
                                    <label class="form-check-label" for="doc_partida">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        Partida de Nacimiento
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="doc_titulo" <?php echo ($estudiante['semestre_actual'] >= 9) ? 'checked' : ''; ?> disabled>
                                    <label class="form-check-label" for="doc_titulo">
                                        <?php if ($estudiante['semestre_actual'] >= 9): ?>
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        <?php else: ?>
                                        <i class="fas fa-clock text-warning me-2"></i>
                                        <?php endif; ?>
                                        Título Bachiller
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="doc_notas" checked disabled>
                                    <label class="form-check-label" for="doc_notas">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        Notas Certificadas
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="doc_conducta" checked disabled>
                                    <label class="form-check-label" for="doc_conducta">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        Certificado de Conducta
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para enviar mensaje -->
<div class="modal fade" id="modalEnviarMensaje" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enviar Mensaje a <?php echo $estudiante['nombres']; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formEnviarMensaje">
                    <input type="hidden" name="estudiante_id" value="<?php echo $estudiante_id; ?>">
                    
                    <div class="mb-3">
                        <label for="asunto" class="form-label">Asunto *</label>
                        <input type="text" class="form-control" id="asunto" name="asunto" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="mensaje" class="form-label">Mensaje *</label>
                        <textarea class="form-control" id="mensaje" name="mensaje" rows="5" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="enviar_email" name="enviar_email" checked>
                            <label class="form-check-label" for="enviar_email">
                                Enviar también por email
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-unexca" onclick="enviarMensajeConfirmar()">
                    <i class="fas fa-paper-plane me-2"></i>Enviar Mensaje
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function enviarMensaje() {
    const modal = new bootstrap.Modal(document.getElementById('modalEnviarMensaje'));
    modal.show();
}

function enviarMensajeConfirmar() {
    const form = document.getElementById('formEnviarMensaje');
    const formData = new FormData(form);
    
    UNEXCA.Utils.showLoading('#modalEnviarMensaje .modal-content', 'Enviando mensaje...');
    
    fetch('ajax/enviar_mensaje_estudiante.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        UNEXCA.Utils.hideLoading('#modalEnviarMensaje .modal-content');
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Mensaje enviado!',
                text: data.message,
                confirmButtonText: 'Continuar'
            }).then(() => {
                bootstrap.Modal.getInstance(document.getElementById('modalEnviarMensaje')).hide();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message
            });
        }
    })
    .catch(error => {
        UNEXCA.Utils.hideLoading('#modalEnviarMensaje .modal-content');
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Ocurrió un error al enviar el mensaje'
        });
    });
}

function generarCertificado() {
    Swal.fire({
        title: 'Generar Certificado',
        text: '¿Qué tipo de certificado desea generar?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Certificado de Estudios',
        showDenyButton: true,
        denyButtonText: 'Constancia de Matrícula',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            UNEXCA.Utils.showNotification('Generando certificado de estudios...', 'info');
            
            setTimeout(() => {
                window.open(`certificados.php?tipo=estudios&estudiante_id=<?php echo $estudiante_id; ?>`, '_blank');
            }, 1000);
        } else if (result.isDenied) {
            UNEXCA.Utils.showNotification('Generando constancia de matrícula...', 'info');
            
            setTimeout(() => {
                window.open(`certificados.php?tipo=matricula&estudiante_id=<?php echo $estudiante_id; ?>`, '_blank');
            }, 1000);
        }
    });
}

function registrarPago() {
    window.location.href = `finanzas.php?estudiante_id=<?php echo $estudiante_id; ?>&action=nuevo`;
}

function verHistorialCompleto() {
    window.location.href = `reportes.php?tipo=estudiante&id=<?php echo $estudiante_id; ?>&formato=pdf`;
}

function marcarPagoComoPagado(pagoId) {
    Swal.fire({
        title: 'Marcar como pagado',
        text: '¿Está seguro de marcar este pago como pagado?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, marcar como pagado',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`ajax/marcar_pago_pagado.php?id=${pagoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Pago marcado!',
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

function verDetallesCurso(cursoId) {
    window.open(`cursos.php?action=ver&id=${cursoId}`, '_blank');
}

function matricularEnCurso(cursoId) {
    Swal.fire({
        title: 'Matricular en curso',
        text: '¿Está seguro de matricular al estudiante en este curso?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, matricular',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`ajax/matricular_estudiante.php?estudiante_id=<?php echo $estudiante_id; ?>&curso_id=${cursoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Matrícula exitosa!',
                        html: `Estudiante matriculado en el curso<br>
                               <small>Sección: ${data.seccion}</small>`,
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

function subirDocumento() {
    Swal.fire({
        title: 'Subir Documento',
        html: `
            <form id="formSubirDocumento">
                <input type="hidden" name="estudiante_id" value="<?php echo $estudiante_id; ?>">
                <div class="mb-3">
                    <label for="tipo_documento" class="form-label">Tipo de Documento</label>
                    <select class="form-select" id="tipo_documento" name="tipo_documento" required>
                        <option value="">Seleccionar tipo</option>
                        <option value="academico">Académico</option>
                        <option value="personal">Personal</option>
                        <option value="legal">Legal</option>
                        <option value="otros">Otros</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="nombre_documento" class="form-label">Nombre del Documento</label>
                    <input type="text" class="form-control" id="nombre_documento" name="nombre_documento" required>
                </div>
                <div class="mb-3">
                    <label for="archivo_documento" class="form-label">Archivo</label>
                    <input type="file" class="form-control" id="archivo_documento" name="archivo_documento" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Subir',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const form = document.getElementById('formSubirDocumento');
            const formData = new FormData(form);
            
            return fetch('ajax/subir_documento.php', {
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
                title: '¡Documento subido!',
                text: 'El documento ha sido subido exitosamente',
                confirmButtonText: 'Continuar'
            }).then(() => {
                cargarDocumentos();
            });
        }
    });
}

function cargarDocumentos() {
    fetch(`ajax/obtener_documentos.php?estudiante_id=<?php echo $estudiante_id; ?>`)
    .then(response => response.json())
    .then(data => {
        const tabla = document.getElementById('listaDocumentos');
        tabla.innerHTML = '';
        
        if (data.length === 0) {
            tabla.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-3">
                        <i class="fas fa-folder-open fa-2x text-muted mb-2"></i>
                        <p class="text-muted">No hay documentos subidos</p>
                    </td>
                </tr>
            `;
            return;
        }
        
        data.forEach(doc => {
            const fila = document.createElement('tr');
            fila.innerHTML = `
                <td><span class="badge bg-info">${doc.tipo}</span></td>
                <td>${doc.nombre}</td>
                <td>${doc.tamano}</td>
                <td>${doc.fecha_subida}</td>
                <td><span class="badge bg-${doc.estado === 'aprobado' ? 'success' : 'warning'}">${doc.estado}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="descargarDocumento('${doc.archivo}')">
                        <i class="fas fa-download"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="eliminarDocumento('${doc.id}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tabla.appendChild(fila);
        });
    });
}

function descargarDocumento(archivo) {
    window.open(`ajax/descargar_documento.php?archivo=${encodeURIComponent(archivo)}`, '_blank');
}

function eliminarDocumento(documentoId) {
    Swal.fire({
        title: 'Eliminar Documento',
        text: '¿Está seguro de eliminar este documento?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`ajax/eliminar_documento.php?id=${documentoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: '¡Eliminado!',
                        text: 'El documento ha sido eliminado',
                        icon: 'success'
                    }).then(() => {
                        cargarDocumentos();
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message,
                        icon: 'error'
                    });
                }
            });
        }
    });
}

// Generar gráfico de rendimiento por período
document.addEventListener('DOMContentLoaded', function() {
    <?php
    // Preparar datos para el gráfico de rendimiento
    $periodos = [];
    $promedios = [];
    
    if (!empty($historial)) {
        $periodos_data = [];
        foreach ($historial as $curso) {
            $periodo = $curso['periodo_academico'];
            if (!isset($periodos_data[$periodo])) {
                $periodos_data[$periodo] = ['total' => 0, 'suma' => 0, 'cantidad' => 0];
            }
            if ($curso['nota_final'] !== null) {
                $periodos_data[$periodo]['suma'] += $curso['nota_final'];
                $periodos_data[$periodo]['cantidad']++;
            }
        }
        
        // Calcular promedios por período
        foreach ($periodos_data as $periodo => $data) {
            if ($data['cantidad'] > 0) {
                $periodos[] = $periodo;
                $promedios[] = $data['suma'] / $data['cantidad'];
            }
        }
        
        // Ordenar cronológicamente
        array_multisort($periodos, $promedios);
    }
    ?>
    
    <?php if (!empty($periodos)): ?>
    const ctxRendimiento = document.getElementById('chartRendimientoPeriodo').getContext('2d');
    
    new Chart(ctxRendimiento, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($periodos); ?>,
            datasets: [{
                label: 'Promedio por Período',
                data: <?php echo json_encode($promedios); ?>,
                borderColor: '#0056b3',
                backgroundColor: 'rgba(0, 86, 179, 0.1)',
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
                    beginAtZero: false,
                    min: 0,
                    max: 20,
                    title: {
                        display: true,
                        text: 'Promedio'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Período Académico'
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    // Cargar documentos al abrir la pestaña
    const documentosTab = document.getElementById('documentos-tab');
    if (documentosTab) {
        documentosTab.addEventListener('shown.bs.tab', function() {
            cargarDocumentos();
        });
    }
});
</script>

<style>
.avatar-profile {
    display: flex;
    justify-content: center;
    align-items: center;
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

.list-group-item {
    border: none;
    padding: 0.75rem 0;
}
</style>

<?php include '../../includes/footer.php'; ?>