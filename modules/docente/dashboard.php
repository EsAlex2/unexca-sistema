<?php
// modules/docentes/dashboard.php
require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SESSION['rol'] != 'docente') {
    header('Location: ../../index.php');
    exit();
}

$page_title = 'Dashboard Docente';

$db = new Database();
$conn = $db->getConnection();

// Obtener información del docente
$query = "SELECT d.*, u.email 
          FROM docentes d 
          JOIN usuarios u ON d.usuario_id = u.id 
          WHERE u.id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$docente = $stmt->fetch(PDO::FETCH_ASSOC);

$docente_id = $docente['id'];   

// Obtener estadísticas
// Total de cursos asignados
$query = "SELECT COUNT(*) as total_cursos 
          FROM secciones 
          WHERE docente_id = :docente_id 
          AND estado IN ('en_progreso', 'abierta')";
$stmt = $conn->prepare($query);
$stmt->bindParam(':docente_id', $docente_id);
$stmt->execute();
$total_cursos = $stmt->fetch(PDO::FETCH_ASSOC)['total_cursos'];

// Total de estudiantes
$query = "SELECT COUNT(DISTINCT m.estudiante_id) as total_estudiantes 
          FROM secciones s 
          JOIN matriculas m ON s.id = m.seccion_id 
          WHERE s.docente_id = :docente_id 
          AND s.estado IN ('en_progreso', 'abierta')
          AND m.estado = 'matriculado'";
$stmt = $conn->prepare($query);
$stmt->bindParam(':docente_id', $docente_id);
$stmt->execute();
$total_estudiantes = $stmt->fetch(PDO::FETCH_ASSOC)['total_estudiantes'];

// Calificaciones pendientes
$query = "SELECT COUNT(DISTINCT m.estudiante_id) as pendientes
          FROM secciones s 
          JOIN matriculas m ON s.id = m.seccion_id 
          WHERE s.docente_id = :docente_id 
          AND s.estado = 'en_progreso'
          AND m.estado = 'matriculado'
          AND m.id NOT IN (
              SELECT matricula_id FROM calificaciones 
              JOIN tipos_evaluacion ON calificaciones.tipo_evaluacion_id = tipos_evaluacion.id 
              WHERE tipos_evaluacion.seccion_id = s.id
          )";
$stmt = $conn->prepare($query);
$stmt->bindParam(':docente_id', $docente_id);
$stmt->execute();
$calificaciones_pendientes = $stmt->fetch(PDO::FETCH_ASSOC)['pendientes'];

// Cursos asignados
$query = "SELECT s.*, c.nombre as curso_nombre, c.codigo as curso_codigo, 
                 c.creditos, COUNT(m.id) as total_estudiantes
          FROM secciones s 
          JOIN cursos c ON s.curso_id = c.id 
          LEFT JOIN matriculas m ON s.id = m.seccion_id AND m.estado = 'matriculado'
          WHERE s.docente_id = :docente_id 
          AND s.estado IN ('en_progreso', 'abierta')
          GROUP BY s.id 
          ORDER BY s.periodo_academico DESC, c.nombre";
$stmt = $conn->prepare($query);
$stmt->bindParam(':docente_id', $docente_id);
$stmt->execute();
$cursos_asignados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Próximas actividades (simplificado)
$actividades = [
    ['fecha' => date('Y-m-d', strtotime('+2 days')), 'titulo' => 'Entrega parcial Matemáticas', 'curso' => 'MAT-101'],
    ['fecha' => date('Y-m-d', strtotime('+5 days')), 'titulo' => 'Revisión de trabajos Física', 'curso' => 'FIS-201'],
    ['fecha' => date('Y-m-d', strtotime('+7 days')), 'titulo' => 'Junta de profesores', 'curso' => 'General'],
];

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
                                <i class="fas fa-chalkboard-teacher fa-3x text-primary"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h3 class="mb-1"><?php echo $docente['nombres'] . ' ' . $docente['apellidos']; ?></h3>
                        <p class="text-muted mb-2"><?php echo $docente['codigo_docente']; ?> - <?php echo $docente['titulo_academico']; ?></p>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><i class="fas fa-book me-2"></i><?php echo $docente['especialidad']; ?></p>
                                <p class="mb-1"><i class="fas fa-building me-2"></i>Departamento: <?php echo $docente['departamento_id'] ?? 'No asignado'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><i class="fas fa-envelope me-2"></i><?php echo $docente['email']; ?></p>
                                <p class="mb-1"><i class="fas fa-phone me-2"></i><?php echo $docente['telefono'] ?? 'No registrado'; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="bg-light p-3 rounded">
                            <small class="text-muted d-block">Estado</small>
                            <span class="badge bg-success fs-6">Activo</span>
                            <br>
                            <small class="text-muted">Desde: <?php echo date('d/m/Y', strtotime($docente['fecha_contratacion'])); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Widgets de estadísticas -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="widget widget-1">
            <div class="row no-gutters align-items-center">
                <div class="col me-2">
                    <div class="text-uppercase mb-1">Cursos Activos</div>
                    <div class="h5 mb-0"><?php echo $total_cursos; ?></div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-book-open fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="widget widget-2">
            <div class="row no-gutters align-items-center">
                <div class="col me-2">
                    <div class="text-uppercase mb-1">Estudiantes</div>
                    <div class="h5 mb-0"><?php echo $total_estudiantes; ?></div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-users fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="widget widget-3">
            <div class="row no-gutters align-items-center">
                <div class="col me-2">
                    <div class="text-uppercase mb-1">Calif. Pendientes</div>
                    <div class="h5 mb-0"><?php echo $calificaciones_pendientes; ?></div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-clipboard-check fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="widget widget-4">
            <div class="row no-gutters align-items-center">
                <div class="col me-2">
                    <div class="text-uppercase mb-1">Horas Semanales</div>
                    <div class="h5 mb-0">24</div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-clock fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Cursos asignados -->
    <div class="col-xl-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Mis Cursos</h5>
                <a href="cursos.php" class="btn btn-sm btn-unexca">Ver Todos</a>
            </div>
            <div class="card-body">
                <?php if (empty($cursos_asignados)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No tienes cursos asignados</p>
                    <small class="text-muted">Contacta con la coordinación académica</small>
                </div>
                <?php else: ?>
                <div class="row">
                    <?php foreach ($cursos_asignados as $curso): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100 border">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h6 class="mb-1"><?php echo $curso['curso_nombre']; ?></h6>
                                        <small class="text-muted"><?php echo $curso['curso_codigo']; ?></small>
                                    </div>
                                    <span class="badge <?php echo $curso['estado'] == 'en_progreso' ? 'bg-success' : 'bg-warning'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $curso['estado'])); ?>
                                    </span>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted d-block">
                                        <i class="fas fa-calendar me-1"></i><?php echo $curso['periodo_academico']; ?>
                                    </small>
                                    <small class="text-muted d-block">
                                        <i class="fas fa-clock me-1"></i><?php echo $curso['horario']; ?>
                                    </small>
                                    <small class="text-muted d-block">
                                        <i class="fas fa-door-open me-1"></i><?php echo $curso['aula']; ?>
                                    </small>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted">
                                            <i class="fas fa-users me-1"></i>
                                            <?php echo $curso['total_estudiantes']; ?> estudiantes
                                        </small>
                                    </div>
                                    <div class="btn-group">
                                        <a href="../calificaciones/libro_calificaciones.php?seccion_id=<?php echo $curso['id']; ?>" 
                                           class="btn btn-sm btn-outline-unexca">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="asistencia.php?seccion_id=<?php echo $curso['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-clipboard-list"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Próximas actividades -->
    <div class="col-xl-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Próximas Actividades</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($actividades as $actividad): 
                        $dias_restantes = floor((strtotime($actividad['fecha']) - time()) / (60 * 60 * 24));
                        $clase = ($dias_restantes <= 1) ? 'list-group-item-danger' : 
                                (($dias_restantes <= 3) ? 'list-group-item-warning' : '');
                    ?>
                    <div class="list-group-item list-group-item-action <?php echo $clase; ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo $actividad['titulo']; ?></h6>
                            <small><?php echo date('d/m', strtotime($actividad['fecha'])); ?></small>
                        </div>
                        <p class="mb-1">
                            <small class="text-muted">Curso: <?php echo $actividad['curso']; ?></small>
                        </p>
                        <small class="text-muted">
                            <?php echo $dias_restantes; ?> días restantes
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-4">
                    <h6>Recordatorios</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fas fa-calendar-check text-success me-2"></i>
                            <small>Revisión de syllabus antes del 15/03</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-file-alt text-primary me-2"></i>
                            <small>Entrega de planificaciones semanales</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-chart-bar text-warning me-2"></i>
                            <small>Reporte de asistencia mensual</small>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Acciones rápidas -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">Acciones Rápidas</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="../calificaciones/libro_calificaciones.php" class="btn btn-unexca">
                        <i class="fas fa-graduation-cap me-2"></i>Libro de Calificaciones
                    </a>
                    <a href="asistencia.php" class="btn btn-outline-unexca">
                        <i class="fas fa-clipboard-list me-2"></i>Registro de Asistencia
                    </a>
                    <a href="../comunicacion/mensajes.php?action=nuevo" class="btn btn-outline-primary">
                        <i class="fas fa-envelope me-2"></i>Nuevo Mensaje
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Calendario de actividades -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Calendario Académico</h5>
            </div>
            <div class="card-body">
                <div id="calendarioDocente"></div>
            </div>
        </div>
    </div>
</div>

<!-- FullCalendar CSS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css" rel="stylesheet">
<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/locales-all.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendarioDocente');
    
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: [
            {
                title: 'Entrega Parcial - Matemáticas',
                start: '<?php echo date('Y-m-d', strtotime('+2 days')); ?>',
                color: '#dc3545'
            },
            {
                title: 'Junta de Profesores',
                start: '<?php echo date('Y-m-d', strtotime('+7 days')); ?>',
                color: '#007bff'
            },
            {
                title: 'Fecha Límite Calificaciones',
                start: '<?php echo date('Y-m-15'); ?>',
                color: '#28a745'
            },
            {
                title: 'Clase Regular - Física',
                start: '<?php echo date('Y-m-d'); ?>T08:00:00',
                end: '<?php echo date('Y-m-d'); ?>T10:00:00',
                color: '#6c757d'
            }
        ],
        eventClick: function(info) {
            alert('Evento: ' + info.event.title);
        }
    });
    
    calendar.render();
});
</script>

<?php include '../../includes/footer.php'; ?>