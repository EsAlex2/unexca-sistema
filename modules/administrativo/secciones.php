<?php
// modules/administrativo/secciones.php
require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SESSION['rol'] != 'administrador') {
    header('Location: ../../index.php');
    exit();
}

$page_title = 'Gestión de Secciones';

$db = new Database();
$conn = $db->getConnection();

// Manejar acciones
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;
$tipo = $_GET['tipo'] ?? 'activas'; // activas, historico, sin_docente

// Crear/editar sección
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_seccion'])) {
    $curso_id = $_POST['curso_id'];
    $docente_id = $_POST['docente_id'] ?: null;
    $codigo_seccion = sanitize($_POST['codigo_seccion']);
    $periodo_academico = sanitize($_POST['periodo_academico']);
    $horario = sanitize($_POST['horario']);
    $aula = sanitize($_POST['aula']);
    $cupo_maximo = intval($_POST['cupo_maximo']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $estado = $_POST['estado'] ?? 'abierta';
    $modalidad = $_POST['modalidad'] ?? 'presencial';
    $descripcion = sanitize($_POST['descripcion'] ?? '');
    
    // Validar que no haya conflicto de horario para el docente
    if ($docente_id) {
        $query = "SELECT COUNT(*) as conflictos 
                  FROM secciones 
                  WHERE docente_id = :docente_id 
                  AND periodo_academico = :periodo 
                  AND estado IN ('abierta', 'en_progreso')
                  AND horario LIKE :horario 
                  AND id != :excluir_id";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':docente_id', $docente_id);
        $stmt->bindParam(':periodo', $periodo_academico);
        $stmt->bindValue(':horario', "%" . substr($horario, 0, 5) . "%");
        $stmt->bindValue(':excluir_id', $id > 0 ? $id : 0);
        $stmt->execute();
        
        $conflictos = $stmt->fetch(PDO::FETCH_ASSOC)['conflictos'];
        
        if ($conflictos > 0) {
            $_SESSION['message'] = 'El docente ya tiene una sección asignada en ese horario';
            $_SESSION['message_type'] = 'danger';
            header('Location: secciones.php?action=' . ($id > 0 ? 'editar&id=' . $id : 'nuevo'));
            exit();
        }
    }
    
    if ($id > 0) {
        // Actualizar sección existente
        $query = "UPDATE secciones SET 
                  curso_id = :curso_id,
                  docente_id = :docente_id,
                  codigo_seccion = :codigo_seccion,
                  periodo_academico = :periodo_academico,
                  horario = :horario,
                  aula = :aula,
                  cupo_maximo = :cupo_maximo,
                  fecha_inicio = :fecha_inicio,
                  fecha_fin = :fecha_fin,
                  estado = :estado,
                  modalidad = :modalidad,
                  descripcion = :descripcion
                  WHERE id = :id";
    } else {
        // Crear nueva sección
        $query = "INSERT INTO secciones 
                  (curso_id, docente_id, codigo_seccion, periodo_academico, horario, 
                   aula, cupo_maximo, fecha_inicio, fecha_fin, estado, modalidad, descripcion) 
                  VALUES 
                  (:curso_id, :docente_id, :codigo_seccion, :periodo_academico, :horario,
                   :aula, :cupo_maximo, :fecha_inicio, :fecha_fin, :estado, :modalidad, :descripcion)";
    }
    
    $stmt = $conn->prepare($query);
    $params = [
        ':curso_id' => $curso_id,
        ':docente_id' => $docente_id,
        ':codigo_seccion' => $codigo_seccion,
        ':periodo_academico' => $periodo_academico,
        ':horario' => $horario,
        ':aula' => $aula,
        ':cupo_maximo' => $cupo_maximo,
        ':fecha_inicio' => $fecha_inicio,
        ':fecha_fin' => $fecha_fin,
        ':estado' => $estado,
        ':modalidad' => $modalidad,
        ':descripcion' => $descripcion
    ];
    
    if ($id > 0) {
        $params[':id'] = $id;
    }
    
    if ($stmt->execute($params)) {
        $seccion_id = $id > 0 ? $id : $conn->lastInsertId();
        
        // Crear tipos de evaluación por defecto si es nueva
        if ($id == 0) {
            crearTiposEvaluacionPorDefecto($conn, $seccion_id);
        }
        
        $_SESSION['message'] = 'Sección ' . ($id > 0 ? 'actualizada' : 'creada') . ' exitosamente';
        $_SESSION['message_type'] = 'success';
        header('Location: secciones.php');
        exit();
    } else {
        $_SESSION['message'] = 'Error al guardar la sección';
        $_SESSION['message_type'] = 'danger';
    }
}

// Eliminar sección
if ($action == 'eliminar' && $id > 0) {
    // Verificar si tiene estudiantes matriculados
    $query = "SELECT COUNT(*) as total FROM matriculas WHERE seccion_id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $estudiantes_matriculados = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($estudiantes_matriculados > 0) {
        $_SESSION['message'] = 'No se puede eliminar la sección porque tiene estudiantes matriculados';
        $_SESSION['message_type'] = 'danger';
    } else {
        // Eliminar tipos de evaluación asociados
        $query = "DELETE FROM tipos_evaluacion WHERE seccion_id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        // Eliminar sección
        $query = "DELETE FROM secciones WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = 'Sección eliminada exitosamente';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error al eliminar la sección';
            $_SESSION['message_type'] = 'danger';
        }
    }
    
    header('Location: secciones.php');
    exit();
}

// Cambiar estado de sección
if ($action == 'cambiar_estado' && $id > 0) {
    $nuevo_estado = $_GET['estado'] ?? '';
    $estados_validos = ['abierta', 'cerrada', 'en_progreso', 'finalizada', 'cancelada'];
    
    if (in_array($nuevo_estado, $estados_validos)) {
        $query = "UPDATE secciones SET estado = :estado WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':estado', $nuevo_estado);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = 'Estado de la sección actualizado a ' . $nuevo_estado;
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error al actualizar estado';
            $_SESSION['message_type'] = 'danger';
        }
    }
    
    header('Location: secciones.php');
    exit();
}

// Cerrar matrículas para una sección
if ($action == 'cerrar_matriculas' && $id > 0) {
    $query = "UPDATE secciones SET estado = 'cerrada' WHERE id = :id AND estado = 'abierta'";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    
    if ($stmt->execute() && $stmt->rowCount() > 0) {
        $_SESSION['message'] = 'Matrículas cerradas para esta sección';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'No se pudo cerrar las matrículas';
        $_SESSION['message_type'] = 'warning';
    }
    
    header('Location: secciones.php');
    exit();
}

// Filtros de búsqueda
$search = $_GET['search'] ?? '';
$curso_id = $_GET['curso_id'] ?? '';
$carrera_id = $_GET['carrera_id'] ?? '';
$docente_id = $_GET['docente_id'] ?? '';
$periodo = $_GET['periodo'] ?? '';
$estado_filtro = $_GET['estado'] ?? '';
$modalidad_filtro = $_GET['modalidad'] ?? '';

// Construir consulta según tipo
if ($tipo == 'sin_docente') {
    $query = "SELECT s.*, 
                     c.codigo as curso_codigo, c.nombre as curso_nombre, c.creditos,
                     ca.nombre as carrera_nombre,
                     COUNT(DISTINCT m.id) as estudiantes_matriculados
              FROM secciones s 
              JOIN cursos c ON s.curso_id = c.id 
              JOIN carreras ca ON c.carrera_id = ca.id 
              LEFT JOIN matriculas m ON s.id = m.seccion_id AND m.estado = 'matriculado'
              WHERE s.docente_id IS NULL 
              AND s.estado IN ('abierta', 'en_progreso')";
} elseif ($tipo == 'historico') {
    $query = "SELECT s.*, 
                     c.codigo as curso_codigo, c.nombre as curso_nombre, c.creditos,
                     ca.nombre as carrera_nombre,
                     d.nombres as docente_nombres, d.apellidos as docente_apellidos,
                     COUNT(DISTINCT m.id) as estudiantes_matriculados
              FROM secciones s 
              JOIN cursos c ON s.curso_id = c.id 
              JOIN carreras ca ON c.carrera_id = ca.id 
              LEFT JOIN docentes d ON s.docente_id = d.id 
              LEFT JOIN matriculas m ON s.id = m.seccion_id
              WHERE s.estado IN ('finalizada', 'cancelada')";
} else {
    $query = "SELECT s.*, 
                     c.codigo as curso_codigo, c.nombre as curso_nombre, c.creditos,
                     ca.nombre as carrera_nombre,
                     d.nombres as docente_nombres, d.apellidos as docente_apellidos,
                     COUNT(DISTINCT m.id) as estudiantes_matriculados
              FROM secciones s 
              JOIN cursos c ON s.curso_id = c.id 
              JOIN carreras ca ON c.carrera_id = ca.id 
              LEFT JOIN docentes d ON s.docente_id = d.id 
              LEFT JOIN matriculas m ON s.id = m.seccion_id AND m.estado = 'matriculado'
              WHERE s.estado IN ('abierta', 'en_progreso', 'cerrada')";
}

$params = [];

if (!empty($search)) {
    $query .= " AND (c.nombre LIKE :search OR s.codigo_seccion LIKE :search OR ca.nombre LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($curso_id)) {
    $query .= " AND s.curso_id = :curso_id";
    $params[':curso_id'] = $curso_id;
}

if (!empty($carrera_id)) {
    $query .= " AND c.carrera_id = :carrera_id";
    $params[':carrera_id'] = $carrera_id;
}

if (!empty($docente_id)) {
    $query .= " AND s.docente_id = :docente_id";
    $params[':docente_id'] = $docente_id;
}

if (!empty($periodo)) {
    $query .= " AND s.periodo_academico = :periodo";
    $params[':periodo'] = $periodo;
}

if (!empty($estado_filtro) && $tipo != 'sin_docente' && $tipo != 'historico') {
    $query .= " AND s.estado = :estado";
    $params[':estado'] = $estado_filtro;
}

if (!empty($modalidad_filtro)) {
    $query .= " AND s.modalidad = :modalidad";
    $params[':modalidad'] = $modalidad_filtro;
}

$query .= " GROUP BY s.id ORDER BY s.periodo_academico DESC, c.nombre, s.codigo_seccion";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$secciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener datos para filtros
$carreras = $conn->query("SELECT * FROM carreras WHERE estado = 'activa' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$docentes = $conn->query("SELECT * FROM docentes WHERE estado = 'activo' ORDER BY apellidos, nombres")->fetchAll(PDO::FETCH_ASSOC);

// Obtener cursos según carrera seleccionada
if (!empty($carrera_id)) {
    $query = "SELECT * FROM cursos WHERE carrera_id = :carrera_id AND estado = 'activo' ORDER BY nombre";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':carrera_id', $carrera_id);
    $stmt->execute();
    $cursos_filtro = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $cursos_filtro = [];
}

// Obtener períodos académicos únicos
$periodos = $conn->query("SELECT DISTINCT periodo_academico FROM secciones ORDER BY periodo_academico DESC")->fetchAll(PDO::FETCH_ASSOC);

// Si estamos editando, obtener datos de la sección
$seccion_actual = null;
if ($action == 'editar' && $id > 0) {
    $query = "SELECT * FROM secciones WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $seccion_actual = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($seccion_actual) {
        // Obtener información del curso para mostrar
        $query = "SELECT c.*, ca.nombre as carrera_nombre 
                  FROM cursos c 
                  JOIN carreras ca ON c.carrera_id = ca.id 
                  WHERE c.id = :curso_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':curso_id', $seccion_actual['curso_id']);
        $stmt->execute();
        $curso_info = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

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
        <h5 class="mb-0">Gestión de Secciones</h5>
        <div class="btn-group" role="group">
            <a href="secciones.php?tipo=activas" class="btn btn-<?php echo $tipo == 'activas' ? 'unexca' : 'outline-unexca'; ?>">
                <i class="fas fa-layer-group me-2"></i>Activas
            </a>
            <a href="secciones.php?tipo=sin_docente" class="btn btn-<?php echo $tipo == 'sin_docente' ? 'unexca' : 'outline-unexca'; ?>">
                <i class="fas fa-user-times me-2"></i>Sin Docente
            </a>
            <a href="secciones.php?tipo=historico" class="btn btn-<?php echo $tipo == 'historico' ? 'unexca' : 'outline-unexca'; ?>">
                <i class="fas fa-history me-2"></i>Histórico
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <!-- Filtros -->
        <div class="row mb-4">
            <div class="col-md-12">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="tipo" value="<?php echo $tipo; ?>">
                    
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="search" placeholder="Buscar sección o curso..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <select class="form-select" name="carrera_id" id="filtro_carrera" onchange="actualizarCursosFiltro()">
                            <option value="">Todas las carreras</option>
                            <?php foreach ($carreras as $carrera): ?>
                            <option value="<?php echo $carrera['id']; ?>" 
                                    <?php echo ($carrera_id == $carrera['id']) ? 'selected' : ''; ?>>
                                <?php echo $carrera['nombre']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <select class="form-select" name="curso_id" id="filtro_curso">
                            <option value="">Todos los cursos</option>
                            <?php foreach ($cursos_filtro as $curso): ?>
                            <option value="<?php echo $curso['id']; ?>" 
                                    <?php echo ($curso_id == $curso['id']) ? 'selected' : ''; ?>>
                                <?php echo $curso['nombre']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <select class="form-select" name="periodo">
                            <option value="">Todos los períodos</option>
                            <?php foreach ($periodos as $p): ?>
                            <option value="<?php echo $p['periodo_academico']; ?>" 
                                    <?php echo ($periodo == $p['periodo_academico']) ? 'selected' : ''; ?>>
                                <?php echo $p['periodo_academico']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($tipo == 'activas'): ?>
                    <div class="col-md-2">
                        <select class="form-select" name="estado">
                            <option value="">Todos los estados</option>
                            <option value="abierta" <?php echo ($estado_filtro == 'abierta') ? 'selected' : ''; ?>>Abierta</option>
                            <option value="cerrada" <?php echo ($estado_filtro == 'cerrada') ? 'selected' : ''; ?>>Cerrada</option>
                            <option value="en_progreso" <?php echo ($estado_filtro == 'en_progreso') ? 'selected' : ''; ?>>En Progreso</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <select class="form-select" name="modalidad">
                            <option value="">Todas las modalidades</option>
                            <option value="presencial" <?php echo ($modalidad_filtro == 'presencial') ? 'selected' : ''; ?>>Presencial</option>
                            <option value="virtual" <?php echo ($modalidad_filtro == 'virtual') ? 'selected' : ''; ?>>Virtual</option>
                            <option value="hibrida" <?php echo ($modalidad_filtro == 'hibrida') ? 'selected' : ''; ?>>Híbrida</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-12 mt-3">
                        <button type="submit" class="btn btn-unexca me-2">
                            <i class="fas fa-filter me-1"></i>Filtrar
                        </button>
                        <a href="secciones.php?tipo=<?php echo $tipo; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-redo me-1"></i>Limpiar
                        </a>
                        
                        <?php if ($action != 'nuevo' && $action != 'editar'): ?>
                        <a href="secciones.php?action=nuevo" class="btn btn-unexca float-end">
                            <i class="fas fa-plus me-2"></i>Nueva Sección
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($action == 'nuevo' || $action == 'editar'): ?>
        
        <!-- Formulario de sección -->
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><?php echo $action == 'editar' ? 'Editar Sección' : 'Nueva Sección'; ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="formSeccion">
                            <input type="hidden" name="guardar_seccion" value="1">
                            <?php if ($action == 'editar'): ?>
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="curso_id" class="form-label">Curso *</label>
                                    <select class="form-select" id="curso_id" name="curso_id" required 
                                            onchange="actualizarInfoCurso()">
                                        <option value="">Seleccionar curso</option>
                                        <?php
                                        $query_cursos = "SELECT c.*, ca.nombre as carrera_nombre 
                                                        FROM cursos c 
                                                        JOIN carreras ca ON c.carrera_id = ca.id 
                                                        WHERE c.estado = 'activo' 
                                                        ORDER BY ca.nombre, c.semestre, c.nombre";
                                        $stmt_cursos = $conn->query($query_cursos);
                                        $todos_cursos = $stmt_cursos->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($todos_cursos as $curso_item):
                                        ?>
                                        <option value="<?php echo $curso_item['id']; ?>" 
                                                data-creditos="<?php echo $curso_item['creditos']; ?>"
                                                data-carrera="<?php echo $curso_item['carrera_nombre']; ?>"
                                                data-codigo="<?php echo $curso_item['codigo']; ?>"
                                                <?php echo (($seccion_actual['curso_id'] ?? '') == $curso_item['id']) ? 'selected' : ''; ?>>
                                            <?php echo $curso_item['codigo'] . ' - ' . $curso_item['nombre'] . ' (' . $curso_item['carrera_nombre'] . ')'; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="codigo_seccion" class="form-label">Código de Sección *</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="codigo_seccion" name="codigo_seccion" 
                                               value="<?php echo $seccion_actual['codigo_seccion'] ?? ''; ?>" required>
                                        <button type="button" class="btn btn-outline-secondary" onclick="generarCodigoSeccion()">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Formato automático: CODIGOCURSO-AÑO-SECUENCIA</small>
                                </div>
                            </div>
                            
                            <!-- Información del curso seleccionado -->
                            <div class="card mb-3" id="info_curso" style="<?php echo (isset($curso_info) || $action == 'editar') ? '' : 'display: none;'; ?>">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <small class="text-muted d-block">Código del Curso</small>
                                            <strong id="info_codigo"><?php echo $curso_info['codigo'] ?? ''; ?></strong>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted d-block">Créditos</small>
                                            <strong id="info_creditos"><?php echo $curso_info['creditos'] ?? ''; ?></strong>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted d-block">Carrera</small>
                                            <strong id="info_carrera"><?php echo $curso_info['carrera_nombre'] ?? ''; ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="docente_id" class="form-label">Docente Asignado</label>
                                    <select class="form-select" id="docente_id" name="docente_id">
                                        <option value="">Sin asignar</option>
                                        <?php foreach ($docentes as $docente): ?>
                                        <option value="<?php echo $docente['id']; ?>" 
                                                <?php echo (($seccion_actual['docente_id'] ?? '') == $docente['id']) ? 'selected' : ''; ?>>
                                            <?php echo $docente['nombres'] . ' ' . $docente['apellidos']; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Puede asignarse posteriormente</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="periodo_academico" class="form-label">Período Académico *</label>
                                    <select class="form-select" id="periodo_academico" name="periodo_academico" required>
                                        <?php
                                        $anio_actual = date('Y');
                                        for ($i = -1; $i <= 1; $i++):
                                            $anio = $anio_actual + $i;
                                            for ($semestre = 1; $semestre <= 2; $semestre++):
                                                $valor = $anio . '-' . $semestre;
                                                $texto = $anio . ' - Semestre ' . $semestre;
                                        ?>
                                        <option value="<?php echo $valor; ?>" 
                                                <?php echo (($seccion_actual['periodo_academico'] ?? '') == $valor || (!$seccion_actual && $i == 0 && $semestre == 1)) ? 'selected' : ''; ?>>
                                            <?php echo $texto; ?>
                                        </option>
                                        <?php endfor; endfor; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="horario" class="form-label">Horario *</label>
                                    <textarea class="form-control" id="horario" name="horario" rows="3" required><?php echo $seccion_actual['horario'] ?? ''; ?></textarea>
                                    <small class="text-muted">
                                        Formato: Día HH:MM-HH:MM, Día HH:MM-HH:MM<br>
                                        Ej: Lunes 08:00-10:00, Miércoles 10:00-12:00
                                    </small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="aula" class="form-label">Aula / Enlace Virtual</label>
                                    <input type="text" class="form-control" id="aula" name="aula" 
                                           value="<?php echo $seccion_actual['aula'] ?? ''; ?>"
                                           placeholder="Aula física o enlace de Google Meet/Zoom">
                                    <small class="text-muted">Depende de la modalidad seleccionada</small>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="cupo_maximo" class="form-label">Cupo Máximo *</label>
                                    <input type="number" class="form-control" id="cupo_maximo" name="cupo_maximo" 
                                           min="1" max="100" value="<?php echo $seccion_actual['cupo_maximo'] ?? 30; ?>" required>
                                    <small class="text-muted">Máximo de estudiantes permitidos</small>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="fecha_inicio" class="form-label">Fecha Inicio *</label>
                                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                                           value="<?php echo $seccion_actual['fecha_inicio'] ?? date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="fecha_fin" class="form-label">Fecha Fin *</label>
                                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                                           value="<?php echo $seccion_actual['fecha_fin'] ?? date('Y-m-d', strtotime('+4 months')); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="estado" class="form-label">Estado *</label>
                                    <select class="form-select" id="estado" name="estado" required>
                                        <option value="abierta" <?php echo (($seccion_actual['estado'] ?? '') == 'abierta') ? 'selected' : ''; ?>>Abierta</option>
                                        <option value="cerrada" <?php echo (($seccion_actual['estado'] ?? '') == 'cerrada') ? 'selected' : ''; ?>>Cerrada</option>
                                        <option value="en_progreso" <?php echo (($seccion_actual['estado'] ?? '') == 'en_progreso') ? 'selected' : ''; ?>>En Progreso</option>
                                        <option value="finalizada" <?php echo (($seccion_actual['estado'] ?? '') == 'finalizada') ? 'selected' : ''; ?>>Finalizada</option>
                                        <option value="cancelada" <?php echo (($seccion_actual['estado'] ?? '') == 'cancelada') ? 'selected' : ''; ?>>Cancelada</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="modalidad" class="form-label">Modalidad *</label>
                                    <select class="form-select" id="modalidad" name="modalidad" required 
                                            onchange="actualizarPlaceholderAula()">
                                        <option value="presencial" <?php echo (($seccion_actual['modalidad'] ?? '') == 'presencial') ? 'selected' : ''; ?>>Presencial</option>
                                        <option value="virtual" <?php echo (($seccion_actual['modalidad'] ?? '') == 'virtual') ? 'selected' : ''; ?>>Virtual</option>
                                        <option value="hibrida" <?php echo (($seccion_actual['modalidad'] ?? '') == 'hibrida') ? 'selected' : ''; ?>>Híbrida</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="descripcion" class="form-label">Descripción Adicional</label>
                                    <textarea class="form-control" id="descripcion" name="descripcion" rows="1"><?php echo $seccion_actual['descripcion'] ?? ''; ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Verificación de conflictos -->
                            <div class="alert alert-warning" id="alerta_conflicto" style="display: none;">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <span id="mensaje_conflicto"></span>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="secciones.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Cancelar
                                </a>
                                <button type="submit" class="btn btn-unexca">
                                    <i class="fas fa-save me-2"></i>Guardar Sección
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        
        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card border-left-primary border-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-primary">Total Secciones</h6>
                                <h3 class="mb-0"><?php echo count($secciones); ?></h3>
                            </div>
                            <div class="icon-circle bg-primary">
                                <i class="fas fa-layer-group text-white"></i>
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
                                <h6 class="text-success">Abiertas</h6>
                                <h3 class="mb-0">
                                    <?php 
                                    $abiertas = array_filter($secciones, fn($s) => $s['estado'] == 'abierta');
                                    echo count($abiertas);
                                    ?>
                                </h3>
                            </div>
                            <div class="icon-circle bg-success">
                                <i class="fas fa-door-open text-white"></i>
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
                                <h6 class="text-warning">Sin Docente</h6>
                                <h3 class="mb-0">
                                    <?php 
                                    $sin_docente = array_filter($secciones, fn($s) => empty($s['docente_nombres']));
                                    echo count($sin_docente);
                                    ?>
                                </h3>
                            </div>
                            <div class="icon-circle bg-warning">
                                <i class="fas fa-user-times text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card border-left-info border-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-info">Cupo Promedio</h6>
                                <h3 class="mb-0">
                                    <?php 
                                    $ocupacion_promedio = count($secciones) > 0 ? 
                                        round(array_sum(array_column($secciones, 'estudiantes_matriculados')) / 
                                              array_sum(array_column($secciones, 'cupo_maximo')) * 100, 1) : 0;
                                    echo $ocupacion_promedio; ?>%
                                </h3>
                            </div>
                            <div class="icon-circle bg-info">
                                <i class="fas fa-chart-line text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Listado de secciones -->
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Curso</th>
                        <th>Docente</th>
                        <th>Período</th>
                        <th>Horario/Aula</th>
                        <th>Cupo</th>
                        <th>Modalidad</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($secciones as $seccion): 
                        $cupo_porcentaje = $seccion['cupo_maximo'] > 0 ? 
                            round(($seccion['estudiantes_matriculados'] / $seccion['cupo_maximo']) * 100, 0) : 0;
                        $cupo_color = $cupo_porcentaje >= 90 ? 'danger' : 
                                     ($cupo_porcentaje >= 70 ? 'warning' : 'success');
                        
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
                        
                        $modalidad_icon = '';
                        switch ($seccion['modalidad']) {
                            case 'presencial': $modalidad_icon = 'building'; break;
                            case 'virtual': $modalidad_icon = 'laptop'; break;
                            case 'hibrida': $modalidad_icon = 'blender'; break;
                        }
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo $seccion['codigo_seccion']; ?></strong>
                            <br>
                            <small class="text-muted"><?php echo $seccion['curso_codigo']; ?></small>
                        </td>
                        <td>
                            <h6 class="mb-1"><?php echo $seccion['curso_nombre']; ?></h6>
                            <small class="text-muted">
                                <?php echo $seccion['carrera_nombre']; ?> | 
                                <?php echo $seccion['creditos']; ?> créditos
                            </small>
                        </td>
                        <td>
                            <?php if ($seccion['docente_nombres']): ?>
                            <div class="d-flex align-items-center">
                                <div class="avatar-xs me-2">
                                    <div class="avatar-title bg-light rounded-circle">
                                        <i class="fas fa-chalkboard-teacher text-primary"></i>
                                    </div>
                                </div>
                                <div>
                                    <small class="d-block"><?php echo $seccion['docente_nombres'] . ' ' . $seccion['docente_apellidos']; ?></small>
                                </div>
                            </div>
                            <?php else: ?>
                            <span class="badge bg-warning">Sin asignar</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-info"><?php echo $seccion['periodo_academico']; ?></span>
                            <br>
                            <small class="text-muted">
                                <?php echo date('d/m/Y', strtotime($seccion['fecha_inicio'])); ?> - 
                                <?php echo date('d/m/Y', strtotime($seccion['fecha_fin'])); ?>
                            </small>
                        </td>
                        <td>
                            <small class="d-block"><?php echo $seccion['horario']; ?></small>
                            <small class="text-muted">
                                <i class="fas fa-<?php echo $modalidad_icon; ?> me-1"></i>
                                <?php echo $seccion['aula'] ?: 'Sin definir'; ?>
                            </small>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                    <div class="progress-bar bg-<?php echo $cupo_color; ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo $cupo_porcentaje; ?>%">
                                    </div>
                                </div>
                                <span>
                                    <?php echo $seccion['estudiantes_matriculados']; ?>/<?php echo $seccion['cupo_maximo']; ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $seccion['modalidad'] == 'virtual' ? 'info' : ($seccion['modalidad'] == 'hibrida' ? 'warning' : 'primary'); ?>">
                                <i class="fas fa-<?php echo $modalidad_icon; ?> me-1"></i>
                                <?php echo ucfirst($seccion['modalidad']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $estado_class; ?>">
                                <i class="fas fa-<?php echo $estado_icon; ?> me-1"></i>
                                <?php echo ucfirst($seccion['estado']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="seccion_detalle.php?id=<?php echo $seccion['id']; ?>" 
                                   class="btn btn-outline-primary" title="Ver detalle">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="secciones.php?action=editar&id=<?php echo $seccion['id']; ?>" 
                                   class="btn btn-outline-info" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" 
                                            data-bs-toggle="dropdown" title="Más opciones">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if ($seccion['estado'] == 'abierta'): ?>
                                        <li>
                                            <a class="dropdown-item" href="secciones.php?action=cerrar_matriculas&id=<?php echo $seccion['id']; ?>">
                                                <i class="fas fa-door-closed me-2"></i>Cerrar Matrículas
                                            </a>
                                        </li>
                                        <?php elseif ($seccion['estado'] == 'cerrada'): ?>
                                        <li>
                                            <a class="dropdown-item" href="secciones.php?action=cambiar_estado&id=<?php echo $seccion['id']; ?>&estado=abierta">
                                                <i class="fas fa-door-open me-2"></i>Abrir Matrículas
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php if ($seccion['estado'] == 'en_progreso'): ?>
                                        <li>
                                            <a class="dropdown-item" href="secciones.php?action=cambiar_estado&id=<?php echo $seccion['id']; ?>&estado=finalizada">
                                                <i class="fas fa-check-circle me-2"></i>Marcar como Finalizada
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <li>
                                            <a class="dropdown-item" href="../calificaciones/libro_calificaciones.php?seccion_id=<?php echo $seccion['id']; ?>">
                                                <i class="fas fa-graduation-cap me-2"></i>Libro de Calificaciones
                                            </a>
                                        </li>
                                        
                                        <li>
                                            <a class="dropdown-item" href="lista_estudiantes.php?seccion_id=<?php echo $seccion['id']; ?>">
                                                <i class="fas fa-users me-2"></i>Lista de Estudiantes
                                            </a>
                                        </li>
                                        
                                        <li><hr class="dropdown-divider"></li>
                                        
                                        <li>
                                            <a class="dropdown-item text-danger" 
                                               href="secciones.php?action=eliminar&id=<?php echo $seccion['id']; ?>" 
                                               onclick="return confirm('¿Eliminar esta sección?')">
                                                <i class="fas fa-trash me-2"></i>Eliminar
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Distribución de secciones -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Distribución de Secciones</h6>
                        <div class="dropdown float-end">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                    data-bs-toggle="dropdown">
                                <i class="fas fa-filter"></i> Vista
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="cambiarVistaGrafico('carrera')">Por Carrera</a></li>
                                <li><a class="dropdown-item" href="#" onclick="cambiarVistaGrafico('modalidad')">Por Modalidad</a></li>
                                <li><a class="dropdown-item" href="#" onclick="cambiarVistaGrafico('estado')">Por Estado</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <canvas id="chartDistribucionSecciones" height="150"></canvas>
                            </div>
                            <div class="col-md-4">
                                <div class="list-group" id="leyendaDistribucion">
                                    <!-- La leyenda se actualizará dinámicamente -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
</div>

<!-- Modal para asignar docente rápido -->
<div class="modal fade" id="modalAsignarDocente" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Asignar Docente a Sección</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formAsignarDocente">
                    <input type="hidden" id="seccion_id_asignar" name="seccion_id">
                    
                    <div class="mb-3">
                        <label for="docente_asignar" class="form-label">Seleccionar Docente</label>
                        <select class="form-select" id="docente_asignar" name="docente_id" required>
                            <option value="">Seleccionar docente...</option>
                            <?php foreach ($docentes as $docente): ?>
                            <option value="<?php echo $docente['id']; ?>">
                                <?php echo $docente['nombres'] . ' ' . $docente['apellidos']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Se verificará automáticamente si el docente tiene conflictos de horario.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-unexca" onclick="asignarDocenteConfirmar()">
                    <i class="fas fa-user-check me-2"></i>Asignar Docente
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function actualizarCursosFiltro() {
    const carreraId = document.getElementById('filtro_carrera').value;
    const selectCursos = document.getElementById('filtro_curso');
    
    if (!carreraId) {
        // Mostrar todos los cursos si no hay carrera seleccionada
        fetch('ajax/obtener_cursos.php')
        .then(response => response.json())
        .then(data => {
            selectCursos.innerHTML = '<option value="">Todos los cursos</option>';
            data.forEach(curso => {
                const option = document.createElement('option');
                option.value = curso.id;
                option.textContent = curso.nombre;
                selectCursos.appendChild(option);
            });
        });
        return;
    }
    
    fetch(`ajax/obtener_cursos.php?carrera_id=${carreraId}`)
    .then(response => response.json())
    .then(data => {
        selectCursos.innerHTML = '<option value="">Todos los cursos</option>';
        data.forEach(curso => {
            const option = document.createElement('option');
            option.value = curso.id;
            option.textContent = curso.nombre;
            selectCursos.appendChild(option);
        });
    });
}

function actualizarInfoCurso() {
    const selectCurso = document.getElementById('curso_id');
    const selectedOption = selectCurso.options[selectCurso.selectedIndex];
    
    if (selectedOption.value) {
        document.getElementById('info_curso').style.display = 'block';
        document.getElementById('info_codigo').textContent = selectedOption.dataset.codigo;
        document.getElementById('info_creditos').textContent = selectedOption.dataset.creditos;
        document.getElementById('info_carrera').textContent = selectedOption.dataset.carrera;
        
        // Generar código de sección automático
        generarCodigoSeccion();
    } else {
        document.getElementById('info_curso').style.display = 'none';
    }
}

function generarCodigoSeccion() {
    const selectCurso = document.getElementById('curso_id');
    const selectedOption = selectCurso.options[selectCurso.selectedIndex];
    const periodo = document.getElementById('periodo_academico').value;
    
    if (selectedOption.value && periodo) {
        const codigoCurso = selectedOption.dataset.codigo;
        const [anio, semestre] = periodo.split('-');
        
        // Obtener secuencia para este curso en este período
        fetch(`ajax/obtener_secuencia_seccion.php?curso_id=${selectedOption.value}&periodo=${periodo}`)
        .then(response => response.json())
        .then(data => {
            const secuencia = String(data.secuencia).padStart(2, '0');
            const codigo = `${codigoCurso}-${anio.slice(-2)}${semestre}${secuencia}`;
            document.getElementById('codigo_seccion').value = codigo;
        });
    }
}

function actualizarPlaceholderAula() {
    const modalidad = document.getElementById('modalidad').value;
    const inputAula = document.getElementById('aula');
    
    switch(modalidad) {
        case 'presencial':
            inputAula.placeholder = 'Ej: Aula 101, Edificio A';
            break;
        case 'virtual':
            inputAula.placeholder = 'Ej: https://meet.google.com/xxx-yyyy-zzz';
            break;
        case 'hibrida':
            inputAula.placeholder = 'Ej: Aula 201 / https://meet.google.com/...';
            break;
    }
}

function verificarConflictoHorario() {
    const docenteId = document.getElementById('docente_id').value;
    const periodo = document.getElementById('periodo_academico').value;
    const horario = document.getElementById('horario').value;
    const seccionId = <?php echo $id ?? 0; ?>;
    
    if (!docenteId || !periodo || !horario) return;
    
    fetch('ajax/verificar_conflicto_horario.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            docente_id: docenteId,
            periodo: periodo,
            horario: horario,
            seccion_id: seccionId
        })
    })
    .then(response => response.json())
    .then(data => {
        const alerta = document.getElementById('alerta_conflicto');
        const mensaje = document.getElementById('mensaje_conflicto');
        
        if (data.conflicto) {
            alerta.style.display = 'block';
            mensaje.innerHTML = `Conflicto de horario: ${data.mensaje}`;
        } else {
            alerta.style.display = 'none';
        }
    });
}

// Verificar conflicto al cambiar docente o horario
document.getElementById('docente_id').addEventListener('change', verificarConflictoHorario);
document.getElementById('horario').addEventListener('blur', verificarConflictoHorario);

// Asignar docente desde el listado
function asignarDocente(seccionId) {
    document.getElementById('seccion_id_asignar').value = seccionId;
    const modal = new bootstrap.Modal(document.getElementById('modalAsignarDocente'));
    modal.show();
}

function asignarDocenteConfirmar() {
    const form = document.getElementById('formAsignarDocente');
    const formData = new FormData(form);
    
    UNEXCA.Utils.showLoading('#modalAsignarDocente .modal-content', 'Asignando docente...');
    
    fetch('ajax/asignar_docente.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        UNEXCA.Utils.hideLoading('#modalAsignarDocente .modal-content');
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Docente asignado!',
                text: data.message,
                confirmButtonText: 'Continuar'
            }).then(() => {
                bootstrap.Modal.getInstance(document.getElementById('modalAsignarDocente')).hide();
                location.reload();
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
        UNEXCA.Utils.hideLoading('#modalAsignarDocente .modal-content');
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Ocurrió un error al asignar el docente'
        });
    });
}

// Gráfico de distribución
let distribucionChart;

function inicializarGraficoDistribucion(tipo = 'carrera') {
    const ctx = document.getElementById('chartDistribucionSecciones').getContext('2d');
    const leyenda = document.getElementById('leyendaDistribucion');
    
    <?php
    // Preparar datos para el gráfico
    $datos_carrera = [];
    $datos_modalidad = [];
    $datos_estado = [];
    
    foreach ($secciones as $seccion) {
        // Por carrera
        $carrera = $seccion['carrera_nombre'];
        $datos_carrera[$carrera] = ($datos_carrera[$carrera] ?? 0) + 1;
        
        // Por modalidad
        $modalidad = $seccion['modalidad'];
        $datos_modalidad[$modalidad] = ($datos_modalidad[$modalidad] ?? 0) + 1;
        
        // Por estado
        $estado = $seccion['estado'];
        $datos_estado[$estado] = ($datos_estado[$estado] ?? 0) + 1;
    }
    ?>
    
    let labels, data, backgroundColor;
    
    switch(tipo) {
        case 'carrera':
            labels = <?php echo json_encode(array_keys($datos_carrera)); ?>;
            data = <?php echo json_encode(array_values($datos_carrera)); ?>;
            backgroundColor = ['#0056b3', '#28a745', '#dc3545', '#ffc107', '#17a2b8', '#6c757d'];
            break;
        case 'modalidad':
            labels = <?php echo json_encode(array_keys($datos_modalidad)); ?>;
            data = <?php echo json_encode(array_values($datos_modalidad)); ?>;
            backgroundColor = ['#0056b3', '#17a2b8', '#ffc107'];
            break;
        case 'estado':
            labels = <?php echo json_encode(array_keys($datos_estado)); ?>;
            data = <?php echo json_encode(array_values($datos_estado)); ?>;
            backgroundColor = ['#28a745', '#6c757d', '#0056b3', '#17a2b8', '#dc3545'];
            break;
    }
    
    if (distribucionChart) {
        distribucionChart.destroy();
    }
    
    distribucionChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: backgroundColor,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    
    // Actualizar leyenda
    leyenda.innerHTML = '';
    labels.forEach((label, index) => {
        const item = document.createElement('div');
        item.className = 'list-group-item border-0 py-1';
        item.innerHTML = `
            <div class="d-flex align-items-center">
                <span class="badge me-2" style="background-color: ${backgroundColor[index]}; width: 12px; height: 12px;"></span>
                <span class="flex-grow-1">${label}</span>
                <span class="badge bg-light text-dark">${data[index]}</span>
            </div>
        `;
        leyenda.appendChild(item);
    });
}

function cambiarVistaGrafico(tipo) {
    inicializarGraficoDistribucion(tipo);
}

// Inicializar DataTable y gráfico
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($secciones) && $action != 'nuevo' && $action != 'editar'): ?>
    inicializarGraficoDistribucion('carrera');
    <?php endif; ?>
    
    // Inicializar DataTable
    $('.datatable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
        },
        responsive: true,
        dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
        pageLength: 25,
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: -1 }
        ]
    });
    
    // Si estamos editando, actualizar información inicial
    <?php if ($action == 'editar' && isset($seccion_actual)): ?>
    actualizarInfoCurso();
    actualizarPlaceholderAula();
    <?php endif; ?>
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

.avatar-xs {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
}

.border-left-primary { border-left-color: #0056b3 !important; }
.border-left-success { border-left-color: #28a745 !important; }
.border-left-warning { border-left-color: #ffc107 !important; }
.border-left-info { border-left-color: #17a2b8 !important; }

.progress {
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    border-radius: 10px;
}
</style>

<?php
// Función para crear tipos de evaluación por defecto
function crearTiposEvaluacionPorDefecto($conn, $seccion_id) {
    $tipos = [
        ['nombre' => 'Parcial 1', 'peso' => 30, 'descripcion' => 'Primer examen parcial'],
        ['nombre' => 'Parcial 2', 'peso' => 30, 'descripcion' => 'Segundo examen parcial'],
        ['nombre' => 'Trabajos', 'peso' => 20, 'descripcion' => 'Trabajos y tareas'],
        ['nombre' => 'Participación', 'peso' => 10, 'descripcion' => 'Asistencia y participación'],
        ['nombre' => 'Proyecto Final', 'peso' => 10, 'descripcion' => 'Proyecto final del curso']
    ];
    
    foreach ($tipos as $tipo) {
        $query = "INSERT INTO tipos_evaluacion (seccion_id, nombre, peso, descripcion) 
                  VALUES (:seccion_id, :nombre, :peso, :descripcion)";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':seccion_id' => $seccion_id,
            ':nombre' => $tipo['nombre'],
            ':peso' => $tipo['peso'],
            ':descripcion' => $tipo['descripcion']
        ]);
    }
}

include '../../includes/footer.php';
?>