<?php
// modules/administrativo/cursos.php
require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SESSION['rol'] != 'administrador') {
    header('Location: ../../index.php');
    exit();
}

$page_title = 'Gestión de Cursos y Secciones';

$db = new Database();
$conn = $db->getConnection();

// Manejar acciones
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;
$tipo = $_GET['tipo'] ?? 'cursos'; // cursos o secciones

// Obtener carreras para filtros
$query = "SELECT * FROM carreras WHERE estado = 'activa' ORDER BY nombre";
$stmt = $conn->query($query);
$carreras = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener docentes para asignaciones
$query = "SELECT * FROM docentes WHERE estado = 'activo' ORDER BY apellidos, nombres";
$stmt = $conn->query($query);
$docentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtros
$carrera_id = $_GET['carrera_id'] ?? '';
$search = $_GET['search'] ?? '';
$estado = $_GET['estado'] ?? '';
$periodo = $_GET['periodo'] ?? '';

// Procesar formulario de curso
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_curso'])) {
    $codigo = sanitize($_POST['codigo']);
    $nombre = sanitize($_POST['nombre']);
    $descripcion = sanitize($_POST['descripcion']);
    $creditos = intval($_POST['creditos']);
    $semestre = intval($_POST['semestre']);
    $carrera_id_post = $_POST['carrera_id'];
    $prerequisito_id = $_POST['prerequisito_id'] ?: null;
    $horas_teoria = intval($_POST['horas_teoria']);
    $horas_practica = intval($_POST['horas_practica']);
    $estado_post = $_POST['estado'] ?? 'activo';
    
    if ($id > 0) {
        // Actualizar curso
        $query = "UPDATE cursos SET 
                  codigo = :codigo,
                  nombre = :nombre,
                  descripcion = :descripcion,
                  creditos = :creditos,
                  semestre = :semestre,
                  carrera_id = :carrera_id,
                  prerequisito_id = :prerequisito_id,
                  horas_teoria = :horas_teoria,
                  horas_practica = :horas_practica,
                  estado = :estado
                  WHERE id = :id";
    } else {
        // Crear nuevo curso
        $query = "INSERT INTO cursos 
                  (codigo, nombre, descripcion, creditos, semestre, carrera_id, 
                   prerequisito_id, horas_teoria, horas_practica, estado) 
                  VALUES 
                  (:codigo, :nombre, :descripcion, :creditos, :semestre, :carrera_id,
                   :prerequisito_id, :horas_teoria, :horas_practica, :estado)";
    }
    
    $stmt = $conn->prepare($query);
    $params = [
        ':codigo' => $codigo,
        ':nombre' => $nombre,
        ':descripcion' => $descripcion,
        ':creditos' => $creditos,
        ':semestre' => $semestre,
        ':carrera_id' => $carrera_id_post,
        ':prerequisito_id' => $prerequisito_id,
        ':horas_teoria' => $horas_teoria,
        ':horas_practica' => $horas_practica,
        ':estado' => $estado_post
    ];
    
    if ($id > 0) {
        $params[':id'] = $id;
    }
    
    if ($stmt->execute($params)) {
        $_SESSION['message'] = 'Curso ' . ($id > 0 ? 'actualizado' : 'creado') . ' exitosamente';
        $_SESSION['message_type'] = 'success';
        header('Location: cursos.php?tipo=cursos');
        exit();
    } else {
        $_SESSION['message'] = 'Error al guardar el curso';
        $_SESSION['message_type'] = 'danger';
    }
}

// Procesar formulario de sección
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
    $estado_seccion = $_POST['estado'] ?? 'abierta';
    
    $seccion_id = $_POST['seccion_id'] ?? 0;
    
    if ($seccion_id > 0) {
        // Actualizar sección
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
                  estado = :estado
                  WHERE id = :id";
    } else {
        // Crear nueva sección
        $query = "INSERT INTO secciones 
                  (curso_id, docente_id, codigo_seccion, periodo_academico, horario, 
                   aula, cupo_maximo, fecha_inicio, fecha_fin, estado) 
                  VALUES 
                  (:curso_id, :docente_id, :codigo_seccion, :periodo_academico, :horario,
                   :aula, :cupo_maximo, :fecha_inicio, :fecha_fin, :estado)";
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
        ':estado' => $estado_seccion
    ];
    
    if ($seccion_id > 0) {
        $params[':id'] = $seccion_id;
    }
    
    if ($stmt->execute($params)) {
        $_SESSION['message'] = 'Sección ' . ($seccion_id > 0 ? 'actualizada' : 'creada') . ' exitosamente';
        $_SESSION['message_type'] = 'success';
        header('Location: cursos.php?tipo=secciones');
        exit();
    } else {
        $_SESSION['message'] = 'Error al guardar la sección';
        $_SESSION['message_type'] = 'danger';
    }
}

// Eliminar curso
if ($action == 'eliminar_curso' && $id > 0) {
    // Verificar si tiene secciones
    $query = "SELECT COUNT(*) as total FROM secciones WHERE curso_id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $secciones_asociadas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($secciones_asociadas > 0) {
        $_SESSION['message'] = 'No se puede eliminar el curso porque tiene secciones asociadas';
        $_SESSION['message_type'] = 'danger';
    } else {
        $query = "DELETE FROM cursos WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = 'Curso eliminado exitosamente';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error al eliminar el curso';
            $_SESSION['message_type'] = 'danger';
        }
    }
    
    header('Location: cursos.php?tipo=cursos');
    exit();
}

// Eliminar sección
if ($action == 'eliminar_seccion' && $id > 0) {
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
    
    header('Location: cursos.php?tipo=secciones');
    exit();
}

// Obtener cursos
if ($tipo == 'cursos') {
    $query = "SELECT c.*, ca.nombre as carrera_nombre, 
                     (SELECT nombre FROM cursos WHERE id = c.prerequisito_id) as prerequisito_nombre,
                     COUNT(DISTINCT s.id) as total_secciones,
                     COUNT(DISTINCT m.id) as total_estudiantes
              FROM cursos c 
              LEFT JOIN carreras ca ON c.carrera_id = ca.id 
              LEFT JOIN secciones s ON c.id = s.curso_id 
              LEFT JOIN matriculas m ON s.id = m.seccion_id 
              WHERE 1=1";
    
    $params = [];
    
    if (!empty($carrera_id)) {
        $query .= " AND c.carrera_id = :carrera_id";
        $params[':carrera_id'] = $carrera_id;
    }
    
    if (!empty($search)) {
        $query .= " AND (c.codigo LIKE :search OR c.nombre LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if (!empty($estado)) {
        $query .= " AND c.estado = :estado";
        $params[':estado'] = $estado;
    }
    
    $query .= " GROUP BY c.id ORDER BY c.carrera_id, c.semestre, c.nombre";
    
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener secciones
if ($tipo == 'secciones') {
    $query = "SELECT s.*, c.codigo as curso_codigo, c.nombre as curso_nombre, c.creditos,
                     ca.nombre as carrera_nombre,
                     d.nombres as docente_nombres, d.apellidos as docente_apellidos,
                     COUNT(DISTINCT m.id) as estudiantes_matriculados
              FROM secciones s 
              JOIN cursos c ON s.curso_id = c.id 
              JOIN carreras ca ON c.carrera_id = ca.id 
              LEFT JOIN docentes d ON s.docente_id = d.id 
              LEFT JOIN matriculas m ON s.id = m.seccion_id AND m.estado = 'matriculado'
              WHERE 1=1";
    
    $params = [];
    
    if (!empty($carrera_id)) {
        $query .= " AND c.carrera_id = :carrera_id";
        $params[':carrera_id'] = $carrera_id;
    }
    
    if (!empty($search)) {
        $query .= " AND (c.nombre LIKE :search OR s.codigo_seccion LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if (!empty($estado)) {
        $query .= " AND s.estado = :estado";
        $params[':estado'] = $estado;
    }
    
    if (!empty($periodo)) {
        $query .= " AND s.periodo_academico = :periodo";
        $params[':periodo'] = $periodo;
    }
    
    $query .= " GROUP BY s.id ORDER BY s.periodo_academico DESC, c.nombre, s.codigo_seccion";
    
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $secciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Si estamos editando, obtener datos
$curso_actual = null;
$seccion_actual = null;

if ($action == 'editar' && $id > 0) {
    if ($tipo == 'cursos') {
        $query = "SELECT * FROM cursos WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $curso_actual = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($tipo == 'secciones') {
        $query = "SELECT * FROM secciones WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $seccion_actual = $stmt->fetch(PDO::FETCH_ASSOC);
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
        <h5 class="mb-0">Gestión de <?php echo $tipo == 'cursos' ? 'Cursos' : 'Secciones'; ?></h5>
        <div class="btn-group" role="group">
            <a href="cursos.php?tipo=cursos" class="btn btn-<?php echo $tipo == 'cursos' ? 'unexca' : 'outline-unexca'; ?>">
                <i class="fas fa-book me-2"></i>Cursos
            </a>
            <a href="cursos.php?tipo=secciones" class="btn btn-<?php echo $tipo == 'secciones' ? 'unexca' : 'outline-unexca'; ?>">
                <i class="fas fa-layer-group me-2"></i>Secciones
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
                        <input type="text" class="form-control" name="search" placeholder="Buscar..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <select class="form-select" name="carrera_id">
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
                        <select class="form-select" name="estado">
                            <option value="">Todos los estados</option>
                            <?php if ($tipo == 'cursos'): ?>
                            <option value="activo" <?php echo ($estado == 'activo') ? 'selected' : ''; ?>>Activo</option>
                            <option value="inactivo" <?php echo ($estado == 'inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                            <?php else: ?>
                            <option value="abierta" <?php echo ($estado == 'abierta') ? 'selected' : ''; ?>>Abierta</option>
                            <option value="cerrada" <?php echo ($estado == 'cerrada') ? 'selected' : ''; ?>>Cerrada</option>
                            <option value="en_progreso" <?php echo ($estado == 'en_progreso') ? 'selected' : ''; ?>>En Progreso</option>
                            <option value="finalizada" <?php echo ($estado == 'finalizada') ? 'selected' : ''; ?>>Finalizada</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <?php if ($tipo == 'secciones'): ?>
                    <div class="col-md-3">
                        <select class="form-select" name="periodo">
                            <option value="">Todos los períodos</option>
                            <?php
                            $query = "SELECT DISTINCT periodo_academico FROM secciones ORDER BY periodo_academico DESC";
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
                    <?php endif; ?>
                    
                    <div class="col-md-12 mt-3">
                        <button type="submit" class="btn btn-unexca me-2">
                            <i class="fas fa-filter me-2"></i>Filtrar
                        </button>
                        <a href="cursos.php?tipo=<?php echo $tipo; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-redo me-2"></i>Limpiar
                        </a>
                        
                        <?php if ($action != 'nuevo' && $action != 'editar'): ?>
                        <a href="cursos.php?tipo=<?php echo $tipo; ?>&action=nuevo" class="btn btn-unexca float-end">
                            <i class="fas fa-plus me-2"></i>Nuevo <?php echo $tipo == 'cursos' ? 'Curso' : 'Sección'; ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($action == 'nuevo' || $action == 'editar'): ?>
        
            <?php if ($tipo == 'cursos'): ?>
            <!-- Formulario de curso -->
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><?php echo $action == 'editar' ? 'Editar Curso' : 'Nuevo Curso'; ?></h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="guardar_curso" value="1">
                                <?php if ($action == 'editar'): ?>
                                <input type="hidden" name="id" value="<?php echo $id; ?>">
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="codigo" class="form-label">Código del Curso *</label>
                                        <input type="text" class="form-control" id="codigo" name="codigo" 
                                               value="<?php echo $curso_actual['codigo'] ?? ''; ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="nombre" class="form-label">Nombre del Curso *</label>
                                        <input type="text" class="form-control" id="nombre" name="nombre" 
                                               value="<?php echo $curso_actual['nombre'] ?? ''; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="descripcion" class="form-label">Descripción</label>
                                    <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?php echo $curso_actual['descripcion'] ?? ''; ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="creditos" class="form-label">Créditos *</label>
                                        <input type="number" class="form-control" id="creditos" name="creditos" 
                                               min="1" max="10" value="<?php echo $curso_actual['creditos'] ?? 3; ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="semestre" class="form-label">Semestre *</label>
                                        <input type="number" class="form-control" id="semestre" name="semestre" 
                                               min="1" max="10" value="<?php echo $curso_actual['semestre'] ?? 1; ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="carrera_id" class="form-label">Carrera *</label>
                                        <select class="form-select" id="carrera_id" name="carrera_id" required>
                                            <option value="">Seleccionar carrera</option>
                                            <?php foreach ($carreras as $carrera): ?>
                                            <option value="<?php echo $carrera['id']; ?>" 
                                                    <?php echo (($curso_actual['carrera_id'] ?? '') == $carrera['id']) ? 'selected' : ''; ?>>
                                                <?php echo $carrera['nombre']; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="prerequisito_id" class="form-label">Prerrequisito</label>
                                        <select class="form-select" id="prerequisito_id" name="prerequisito_id">
                                            <option value="">Ninguno</option>
                                            <?php
                                            $query_prerequisitos = "SELECT id, codigo, nombre FROM cursos WHERE id != ? ORDER BY nombre";
                                            $stmt_prerequisitos = $conn->prepare($query_prerequisitos);
                                            $stmt_prerequisitos->execute([$id]);
                                            $prerequisitos = $stmt_prerequisitos->fetchAll(PDO::FETCH_ASSOC);
                                            foreach ($prerequisitos as $prerequisito):
                                            ?>
                                            <option value="<?php echo $prerequisito['id']; ?>" 
                                                    <?php echo (($curso_actual['prerequisito_id'] ?? '') == $prerequisito['id']) ? 'selected' : ''; ?>>
                                                <?php echo $prerequisito['codigo'] . ' - ' . $prerequisito['nombre']; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="horas_teoria" class="form-label">Horas Teoría</label>
                                        <input type="number" class="form-control" id="horas_teoria" name="horas_teoria" 
                                               min="0" value="<?php echo $curso_actual['horas_teoria'] ?? 2; ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="horas_practica" class="form-label">Horas Práctica</label>
                                        <input type="number" class="form-control" id="horas_practica" name="horas_practica" 
                                               min="0" value="<?php echo $curso_actual['horas_practica'] ?? 2; ?>">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="estado" class="form-label">Estado *</label>
                                        <select class="form-select" id="estado" name="estado" required>
                                            <option value="activo" <?php echo (($curso_actual['estado'] ?? '') == 'activo') ? 'selected' : ''; ?>>Activo</option>
                                            <option value="inactivo" <?php echo (($curso_actual['estado'] ?? '') == 'inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <a href="cursos.php?tipo=cursos" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-unexca">
                                        <i class="fas fa-save me-2"></i>Guardar Curso
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php elseif ($tipo == 'secciones'): ?>
            <!-- Formulario de sección -->
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><?php echo $action == 'editar' ? 'Editar Sección' : 'Nueva Sección'; ?></h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="guardar_seccion" value="1">
                                <?php if ($action == 'editar'): ?>
                                <input type="hidden" name="seccion_id" value="<?php echo $id; ?>">
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="curso_id" class="form-label">Curso *</label>
                                        <select class="form-select" id="curso_id" name="curso_id" required>
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
                                                    <?php echo (($seccion_actual['curso_id'] ?? '') == $curso_item['id']) ? 'selected' : ''; ?>>
                                                <?php echo $curso_item['codigo'] . ' - ' . $curso_item['nombre'] . ' (' . $curso_item['carrera_nombre'] . ')'; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="codigo_seccion" class="form-label">Código de Sección *</label>
                                        <input type="text" class="form-control" id="codigo_seccion" name="codigo_seccion" 
                                               value="<?php echo $seccion_actual['codigo_seccion'] ?? ''; ?>" required>
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
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="periodo_academico" class="form-label">Período Académico *</label>
                                        <input type="text" class="form-control" id="periodo_academico" name="periodo_academico" 
                                               value="<?php echo $seccion_actual['periodo_academico'] ?? date('Y') . '-1'; ?>" required>
                                        <small class="text-muted">Formato: AÑO-SEMESTRE (ej: 2024-1)</small>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="horario" class="form-label">Horario *</label>
                                        <textarea class="form-control" id="horario" name="horario" rows="2" required><?php echo $seccion_actual['horario'] ?? ''; ?></textarea>
                                        <small class="text-muted">Ej: Lunes 8:00-10:00, Miércoles 10:00-12:00</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="aula" class="form-label">Aula</label>
                                        <input type="text" class="form-control" id="aula" name="aula" 
                                               value="<?php echo $seccion_actual['aula'] ?? ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="cupo_maximo" class="form-label">Cupo Máximo *</label>
                                        <input type="number" class="form-control" id="cupo_maximo" name="cupo_maximo" 
                                               min="1" max="100" value="<?php echo $seccion_actual['cupo_maximo'] ?? 30; ?>" required>
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
                                    <div class="col-md-6 mb-3">
                                        <label for="estado" class="form-label">Estado *</label>
                                        <select class="form-select" id="estado" name="estado" required>
                                            <option value="abierta" <?php echo (($seccion_actual['estado'] ?? '') == 'abierta') ? 'selected' : ''; ?>>Abierta</option>
                                            <option value="cerrada" <?php echo (($seccion_actual['estado'] ?? '') == 'cerrada') ? 'selected' : ''; ?>>Cerrada</option>
                                            <option value="en_progreso" <?php echo (($seccion_actual['estado'] ?? '') == 'en_progreso') ? 'selected' : ''; ?>>En Progreso</option>
                                            <option value="finalizada" <?php echo (($seccion_actual['estado'] ?? '') == 'finalizada') ? 'selected' : ''; ?>>Finalizada</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <a href="cursos.php?tipo=secciones" class="btn btn-secondary">
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
            <?php endif; ?>
        
        <?php else: ?>
        
            <?php if ($tipo == 'cursos'): ?>
            <!-- Listado de cursos -->
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Curso</th>
                            <th>Carrera</th>
                            <th>Semestre</th>
                            <th>Créditos</th>
                            <th>Prerrequisito</th>
                            <th>Estadísticas</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cursos as $curso): ?>
                        <tr>
                            <td>
                                <strong><?php echo $curso['codigo']; ?></strong>
                            </td>
                            <td>
                                <h6 class="mb-1"><?php echo $curso['nombre']; ?></h6>
                                <?php if ($curso['descripcion']): ?>
                                <small class="text-muted"><?php echo substr($curso['descripcion'], 0, 100); ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $curso['carrera_nombre']; ?></td>
                            <td>
                                <span class="badge bg-info"><?php echo $curso['semestre']; ?>°</span>
                            </td>
                            <td>
                                <span class="badge bg-success"><?php echo $curso['creditos']; ?> créditos</span>
                            </td>
                            <td>
                                <?php if ($curso['prerequisito_nombre']): ?>
                                <small class="text-muted"><?php echo $curso['prerequisito_nombre']; ?></small>
                                <?php else: ?>
                                <span class="text-muted">Ninguno</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <span class="badge bg-primary" title="Secciones">
                                        <i class="fas fa-layer-group me-1"></i><?php echo $curso['total_secciones']; ?>
                                    </span>
                                    <span class="badge bg-success" title="Estudiantes">
                                        <i class="fas fa-users me-1"></i><?php echo $curso['total_estudiantes']; ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $curso['estado'] == 'activo' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($curso['estado']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="cursos.php?tipo=cursos&action=editar&id=<?php echo $curso['id']; ?>" 
                                       class="btn btn-outline-primary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="cursos.php?tipo=secciones&curso_id=<?php echo $curso['id']; ?>" 
                                       class="btn btn-outline-info" title="Ver secciones">
                                        <i class="fas fa-layer-group"></i>
                                    </a>
                                    <a href="cursos.php?tipo=cursos&action=eliminar_curso&id=<?php echo $curso['id']; ?>" 
                                       class="btn btn-outline-danger confirm-delete" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php elseif ($tipo == 'secciones'): ?>
            <!-- Listado de secciones -->
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr>
                            <th>Sección</th>
                            <th>Curso</th>
                            <th>Docente</th>
                            <th>Período</th>
                            <th>Horario/Aula</th>
                            <th>Cupo</th>
                            <th>Estudiantes</th>
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
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo $seccion['codigo_seccion']; ?></strong>
                            </td>
                            <td>
                                <h6 class="mb-1"><?php echo $seccion['curso_nombre']; ?></h6>
                                <small class="text-muted">
                                    <?php echo $seccion['curso_codigo']; ?> | <?php echo $seccion['carrera_nombre']; ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($seccion['docente_nombres']): ?>
                                <?php echo $seccion['docente_nombres'] . ' ' . $seccion['docente_apellidos']; ?>
                                <?php else: ?>
                                <span class="text-danger">Sin asignar</span>
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
                                <small class="text-muted"><?php echo $seccion['aula'] ? 'Aula: ' . $seccion['aula'] : 'Sin aula'; ?></small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                        <div class="progress-bar bg-<?php echo $cupo_color; ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo $cupo_porcentaje; ?>%">
                                        </div>
                                    </div>
                                    <span><?php echo $seccion['estudiantes_matriculados']; ?>/<?php echo $seccion['cupo_maximo']; ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $seccion['estudiantes_matriculados'] > 0 ? 'success' : 'secondary'; ?>">
                                    <?php echo $seccion['estudiantes_matriculados']; ?> matriculados
                                </span>
                            </td>
                            <td>
                                <?php
                                $estado_class = '';
                                switch ($seccion['estado']) {
                                    case 'abierta': $estado_class = 'success'; break;
                                    case 'cerrada': $estado_class = 'secondary'; break;
                                    case 'en_progreso': $estado_class = 'primary'; break;
                                    case 'finalizada': $estado_class = 'info'; break;
                                    default: $estado_class = 'warning';
                                }
                                ?>
                                <span class="badge bg-<?php echo $estado_class; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $seccion['estado'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="cursos.php?tipo=secciones&action=editar&id=<?php echo $seccion['id']; ?>" 
                                       class="btn btn-outline-primary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="../calificaciones/libro_calificaciones.php?seccion_id=<?php echo $seccion['id']; ?>" 
                                       class="btn btn-outline-info" title="Calificaciones">
                                        <i class="fas fa-graduation-cap"></i>
                                    </a>
                                    <a href="cursos.php?tipo=secciones&action=eliminar_seccion&id=<?php echo $seccion['id']; ?>" 
                                       class="btn btn-outline-danger confirm-delete" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.datatable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
        },
        responsive: true,
        pageLength: 25,
        order: [[0, 'asc']],
        dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>'
    });
    
    // Auto-generar código de sección
    $('#curso_id').change(function() {
        if (!$('#codigo_seccion').val()) {
            const cursoId = $(this).val();
            if (cursoId) {
                // Obtener código del curso
                fetch(`ajax/get_curso_info.php?id=${cursoId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Generar código de sección automático (ej: MAT101-01)
                            const baseCodigo = data.codigo;
                            const seccionNum = Math.floor(Math.random() * 99) + 1;
                            $('#codigo_seccion').val(`${baseCodigo}-${seccionNum.toString().padStart(2, '0')}`);
                        }
                    });
            }
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>