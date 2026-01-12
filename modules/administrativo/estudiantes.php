<?php
// modules/administrativo/estudiantes.php
require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SESSION['rol'] != 'administrador') {
    header('Location: ../../index.php');
    exit();
}

$page_title = 'Gestión de Estudiantes';

$db = new Database();
$conn = $db->getConnection();

// Manejar acciones
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

// Crear/editar estudiante
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_estudiante'])) {
    $nombres = sanitize($_POST['nombres']);
    $apellidos = sanitize($_POST['apellidos']);
    $cedula = sanitize($_POST['cedula']);
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $genero = $_POST['genero'];
    $email = sanitize($_POST['email']);
    $telefono = sanitize($_POST['telefono']);
    $direccion = sanitize($_POST['direccion']);
    $carrera_id = $_POST['carrera_id'];
    $semestre_actual = $_POST['semestre_actual'];
    $estado = $_POST['estado'] ?? 'activo';
    $fecha_ingreso = $_POST['fecha_ingreso'];
    
    // Generar código de estudiante automático
    if ($id == 0) {
        $codigo_estudiante = generarCodigoEstudiante($conn, $carrera_id);
    } else {
        $codigo_estudiante = $_POST['codigo_estudiante'] ?? '';
    }
    
    if ($id > 0) {
        // Actualizar estudiante existente
        $query = "UPDATE estudiantes SET 
                  nombres = :nombres,
                  apellidos = :apellidos,
                  cedula = :cedula,
                  codigo_estudiante = :codigo_estudiante,
                  fecha_nacimiento = :fecha_nacimiento,
                  genero = :genero,
                  email = :email,
                  telefono = :telefono,
                  direccion = :direccion,
                  carrera_id = :carrera_id,
                  semestre_actual = :semestre_actual,
                  estado = :estado,
                  fecha_ingreso = :fecha_ingreso
                  WHERE id = :id";
    } else {
        // Crear nuevo estudiante
        $query = "INSERT INTO estudiantes 
                  (nombres, apellidos, cedula, codigo_estudiante, fecha_nacimiento, 
                   genero, email, telefono, direccion, carrera_id, semestre_actual, 
                   estado, fecha_ingreso) 
                  VALUES 
                  (:nombres, :apellidos, :cedula, :codigo_estudiante, :fecha_nacimiento,
                   :genero, :email, :telefono, :direccion, :carrera_id, :semestre_actual,
                   :estado, :fecha_ingreso)";
    }
    
    $stmt = $conn->prepare($query);
    $params = [
        ':nombres' => $nombres,
        ':apellidos' => $apellidos,
        ':cedula' => $cedula,
        ':codigo_estudiante' => $codigo_estudiante,
        ':fecha_nacimiento' => $fecha_nacimiento,
        ':genero' => $genero,
        ':email' => $email,
        ':telefono' => $telefono,
        ':direccion' => $direccion,
        ':carrera_id' => $carrera_id,
        ':semestre_actual' => $semestre_actual,
        ':estado' => $estado,
        ':fecha_ingreso' => $fecha_ingreso
    ];
    
    if ($id > 0) {
        $params[':id'] = $id;
    }
    
    if ($stmt->execute($params)) {
        $estudiante_id = $id > 0 ? $id : $conn->lastInsertId();
        
        // Si es nuevo, crear usuario asociado
        if ($id == 0) {
            $username = strtolower(substr($nombres, 0, 1) . $apellidos);
            $username = preg_replace('/\s+/', '', $username);
            $password = password_hash('Estudiante123', PASSWORD_DEFAULT);
            
            $query_user = "INSERT INTO usuarios (username, password, email, rol, estado) 
                          VALUES (:username, :password, :email, 'estudiante', 'activo')";
            $stmt_user = $conn->prepare($query_user);
            $stmt_user->bindParam(':username', $username);
            $stmt_user->bindParam(':password', $password);
            $stmt_user->bindParam(':email', $email);
            
            if ($stmt_user->execute()) {
                $usuario_id = $conn->lastInsertId();
                
                // Asociar usuario con estudiante
                $query_update = "UPDATE estudiantes SET usuario_id = :usuario_id WHERE id = :estudiante_id";
                $stmt_update = $conn->prepare($query_update);
                $stmt_update->bindParam(':usuario_id', $usuario_id);
                $stmt_update->bindParam(':estudiante_id', $estudiante_id);
                $stmt_update->execute();
            }
        }
        
        $_SESSION['message'] = 'Estudiante ' . ($id > 0 ? 'actualizado' : 'registrado') . ' exitosamente';
        $_SESSION['message_type'] = 'success';
        header('Location: estudiantes.php');
        exit();
    } else {
        $_SESSION['message'] = 'Error al guardar el estudiante';
        $_SESSION['message_type'] = 'danger';
    }
}

// Eliminar estudiante
if ($action == 'eliminar' && $id > 0) {
    // Verificar si tiene matrículas
    $query = "SELECT COUNT(*) as total FROM matriculas WHERE estudiante_id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $matriculas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($matriculas > 0) {
        $_SESSION['message'] = 'No se puede eliminar el estudiante porque tiene matrículas activas';
        $_SESSION['message_type'] = 'danger';
    } else {
        // Obtener usuario_id antes de eliminar
        $query = "SELECT usuario_id FROM estudiantes WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Eliminar estudiante
        $query = "DELETE FROM estudiantes WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            // Eliminar usuario asociado si existe
            if ($estudiante && $estudiante['usuario_id']) {
                $query = "DELETE FROM usuarios WHERE id = :usuario_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':usuario_id', $estudiante['usuario_id']);
                $stmt->execute();
            }
            
            $_SESSION['message'] = 'Estudiante eliminado exitosamente';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error al eliminar el estudiante';
            $_SESSION['message_type'] = 'danger';
        }
    }
    
    header('Location: estudiantes.php');
    exit();
}

// Cambiar estado del estudiante
if ($action == 'cambiar_estado' && $id > 0) {
    $nuevo_estado = $_GET['estado'] ?? '';
    
    if (in_array($nuevo_estado, ['activo', 'inactivo', 'egresado', 'graduado', 'suspendido'])) {
        $query = "UPDATE estudiantes SET estado = :estado WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':estado', $nuevo_estado);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            // También actualizar estado del usuario asociado
            $query = "UPDATE usuarios u 
                      JOIN estudiantes e ON u.id = e.usuario_id 
                      SET u.estado = :estado_usuario 
                      WHERE e.id = :id";
            $stmt = $conn->prepare($query);
            $estado_usuario = ($nuevo_estado == 'activo') ? 'activo' : 'inactivo';
            $stmt->bindParam(':estado_usuario', $estado_usuario);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $_SESSION['message'] = 'Estado del estudiante actualizado a ' . $nuevo_estado;
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error al actualizar estado';
            $_SESSION['message_type'] = 'danger';
        }
    }
    
    header('Location: estudiantes.php');
    exit();
}

// Filtros de búsqueda
$search = $_GET['search'] ?? '';
$carrera_id = $_GET['carrera_id'] ?? '';
$estado = $_GET['estado'] ?? '';
$semestre = $_GET['semestre'] ?? '';
$genero = $_GET['genero'] ?? '';

// Construir consulta
$query = "SELECT e.*, 
                 c.nombre as carrera_nombre,
                 c.codigo as carrera_codigo,
                 COUNT(DISTINCT m.id) as total_matriculas,
                 COUNT(DISTINCT CASE WHEN m2.nota_final >= 10 THEN m2.id END) as cursos_aprobados,
                 AVG(m2.nota_final) as promedio_general,
                 (SELECT SUM(cu.creditos) 
                  FROM matriculas m3 
                  JOIN secciones s ON m3.seccion_id = s.id 
                  JOIN cursos cu ON s.curso_id = cu.id 
                  WHERE m3.estudiante_id = e.id 
                  AND m3.nota_final >= 10) as creditos_aprobados
          FROM estudiantes e 
          JOIN carreras c ON e.carrera_id = c.id 
          LEFT JOIN matriculas m ON e.id = m.estudiante_id 
          LEFT JOIN matriculas m2 ON e.id = m2.estudiante_id AND m2.nota_final IS NOT NULL
          WHERE 1=1";

$params = [];

if (!empty($search)) {
    $query .= " AND (e.nombres LIKE :search OR e.apellidos LIKE :search OR e.codigo_estudiante LIKE :search OR e.cedula LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($carrera_id)) {
    $query .= " AND e.carrera_id = :carrera_id";
    $params[':carrera_id'] = $carrera_id;
}

if (!empty($estado)) {
    $query .= " AND e.estado = :estado";
    $params[':estado'] = $estado;
}

if (!empty($semestre)) {
    $query .= " AND e.semestre_actual = :semestre";
    $params[':semestre'] = $semestre;
}

if (!empty($genero)) {
    $query .= " AND e.genero = :genero";
    $params[':genero'] = $genero;
}

$query .= " GROUP BY e.id ORDER BY e.apellidos, e.nombres";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener carreras para filtro
$query = "SELECT * FROM carreras WHERE estado = 'activa' ORDER BY nombre";
$stmt = $conn->query($query);
$carreras = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si estamos editando, obtener datos del estudiante
$estudiante_actual = null;
if ($action == 'editar' && $id > 0) {
    $query = "SELECT * FROM estudiantes WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $estudiante_actual = $stmt->fetch(PDO::FETCH_ASSOC);
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
        <h5 class="mb-0">Gestión de Estudiantes</h5>
        <div>
            <button type="button" class="btn btn-unexca" data-bs-toggle="modal" data-bs-target="#modalImportarEstudiantes">
                <i class="fas fa-file-import me-2"></i>Importar
            </button>
            <a href="estudiantes.php?action=nuevo" class="btn btn-unexca ms-2">
                <i class="fas fa-plus me-2"></i>Nuevo Estudiante
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <!-- Filtros -->
        <div class="row mb-4">
            <div class="col-md-12">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="search" placeholder="Buscar estudiante..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
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
                    <div class="col-md-2">
                        <select class="form-select" name="estado">
                            <option value="">Todos los estados</option>
                            <option value="activo" <?php echo ($estado == 'activo') ? 'selected' : ''; ?>>Activo</option>
                            <option value="inactivo" <?php echo ($estado == 'inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                            <option value="egresado" <?php echo ($estado == 'egresado') ? 'selected' : ''; ?>>Egresado</option>
                            <option value="graduado" <?php echo ($estado == 'graduado') ? 'selected' : ''; ?>>Graduado</option>
                            <option value="suspendido" <?php echo ($estado == 'suspendido') ? 'selected' : ''; ?>>Suspendido</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="semestre">
                            <option value="">Todos los semestres</option>
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($semestre == $i) ? 'selected' : ''; ?>>
                                <?php echo $i; ?>° Semestre
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="genero">
                            <option value="">Todos</option>
                            <option value="M" <?php echo ($genero == 'M') ? 'selected' : ''; ?>>Masculino</option>
                            <option value="F" <?php echo ($genero == 'F') ? 'selected' : ''; ?>>Femenino</option>
                            <option value="O" <?php echo ($genero == 'O') ? 'selected' : ''; ?>>Otro</option>
                        </select>
                    </div>
                    <div class="col-md-12 mt-3">
                        <button type="submit" class="btn btn-unexca me-2">
                            <i class="fas fa-filter me-1"></i>Filtrar
                        </button>
                        <a href="estudiantes.php" class="btn btn-outline-secondary">
                            <i class="fas fa-redo me-1"></i>Limpiar
                        </a>
                        
                        <?php if (!empty($estudiantes)): ?>
                        <button type="button" class="btn btn-outline-unexca float-end" onclick="exportarEstudiantes()">
                            <i class="fas fa-file-export me-2"></i>Exportar
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($action == 'nuevo' || $action == 'editar'): ?>
        <!-- Formulario de estudiante -->
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><?php echo $action == 'editar' ? 'Editar Estudiante' : 'Nuevo Estudiante'; ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="formEstudiante">
                            <input type="hidden" name="guardar_estudiante" value="1">
                            <?php if ($action == 'editar'): ?>
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                            <input type="hidden" name="codigo_estudiante" value="<?php echo $estudiante_actual['codigo_estudiante']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nombres" class="form-label">Nombres *</label>
                                    <input type="text" class="form-control" id="nombres" name="nombres" 
                                           value="<?php echo $estudiante_actual['nombres'] ?? ''; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="apellidos" class="form-label">Apellidos *</label>
                                    <input type="text" class="form-control" id="apellidos" name="apellidos" 
                                           value="<?php echo $estudiante_actual['apellidos'] ?? ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="cedula" class="form-label">Cédula *</label>
                                    <input type="text" class="form-control" id="cedula" name="cedula" 
                                           value="<?php echo $estudiante_actual['cedula'] ?? ''; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="codigo_estudiante" class="form-label">Código de Estudiante</label>
                                    <input type="text" class="form-control" id="codigo_estudiante" 
                                           value="<?php echo $estudiante_actual['codigo_estudiante'] ?? 'Se generará automáticamente'; ?>" 
                                           disabled>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento *</label>
                                    <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" 
                                           value="<?php echo $estudiante_actual['fecha_nacimiento'] ?? '2000-01-01'; ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="genero" class="form-label">Género *</label>
                                    <select class="form-select" id="genero" name="genero" required>
                                        <option value="M" <?php echo (($estudiante_actual['genero'] ?? '') == 'M') ? 'selected' : ''; ?>>Masculino</option>
                                        <option value="F" <?php echo (($estudiante_actual['genero'] ?? '') == 'F') ? 'selected' : ''; ?>>Femenino</option>
                                        <option value="O" <?php echo (($estudiante_actual['genero'] ?? '') == 'O') ? 'selected' : ''; ?>>Otro</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="fecha_ingreso" class="form-label">Fecha de Ingreso *</label>
                                    <input type="date" class="form-control" id="fecha_ingreso" name="fecha_ingreso" 
                                           value="<?php echo $estudiante_actual['fecha_ingreso'] ?? date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo $estudiante_actual['email'] ?? ''; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="telefono" class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" id="telefono" name="telefono" 
                                           value="<?php echo $estudiante_actual['telefono'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="direccion" class="form-label">Dirección</label>
                                <textarea class="form-control" id="direccion" name="direccion" rows="2"><?php echo $estudiante_actual['direccion'] ?? ''; ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="carrera_id" class="form-label">Carrera *</label>
                                    <select class="form-select" id="carrera_id" name="carrera_id" required>
                                        <option value="">Seleccionar carrera</option>
                                        <?php foreach ($carreras as $carrera): ?>
                                        <option value="<?php echo $carrera['id']; ?>" 
                                                <?php echo (($estudiante_actual['carrera_id'] ?? '') == $carrera['id']) ? 'selected' : ''; ?>>
                                            <?php echo $carrera['nombre']; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="semestre_actual" class="form-label">Semestre Actual *</label>
                                    <select class="form-select" id="semestre_actual" name="semestre_actual" required>
                                        <?php for ($i = 1; $i <= 10; $i++): ?>
                                        <option value="<?php echo $i; ?>" 
                                                <?php echo (($estudiante_actual['semestre_actual'] ?? 1) == $i) ? 'selected' : ''; ?>>
                                            <?php echo $i; ?>° Semestre
                                        </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="estado" class="form-label">Estado *</label>
                                    <select class="form-select" id="estado" name="estado" required>
                                        <option value="activo" <?php echo (($estudiante_actual['estado'] ?? '') == 'activo') ? 'selected' : ''; ?>>Activo</option>
                                        <option value="inactivo" <?php echo (($estudiante_actual['estado'] ?? '') == 'inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                                        <option value="egresado" <?php echo (($estudiante_actual['estado'] ?? '') == 'egresado') ? 'selected' : ''; ?>>Egresado</option>
                                        <option value="graduado" <?php echo (($estudiante_actual['estado'] ?? '') == 'graduado') ? 'selected' : ''; ?>>Graduado</option>
                                        <option value="suspendido" <?php echo (($estudiante_actual['estado'] ?? '') == 'suspendido') ? 'selected' : ''; ?>>Suspendido</option>
                                    </select>
                                </div>
                            </div>
                            
                            <?php if ($action == 'nuevo'): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Nota:</strong> Se generará un usuario automáticamente con la contraseña inicial <code>Estudiante123</code>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="estudiantes.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Cancelar
                                </a>
                                <button type="submit" class="btn btn-unexca">
                                    <i class="fas fa-save me-2"></i>Guardar Estudiante
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Estadísticas rápidas -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card border-left-primary border-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-primary">Total Estudiantes</h6>
                                <h3 class="mb-0"><?php echo count($estudiantes); ?></h3>
                            </div>
                            <div class="icon-circle bg-primary">
                                <i class="fas fa-users text-white"></i>
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
                                <h6 class="text-success">Activos</h6>
                                <h3 class="mb-0">
                                    <?php echo count(array_filter($estudiantes, fn($e) => $e['estado'] == 'activo')); ?>
                                </h3>
                            </div>
                            <div class="icon-circle bg-success">
                                <i class="fas fa-user-check text-white"></i>
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
                                <h6 class="text-warning">Nuevos este mes</h6>
                                <h3 class="mb-0">
                                    <?php 
                                    $nuevos_mes = array_filter($estudiantes, function($e) {
                                        return date('Y-m', strtotime($e['fecha_ingreso'])) == date('Y-m');
                                    });
                                    echo count($nuevos_mes);
                                    ?>
                                </h3>
                            </div>
                            <div class="icon-circle bg-warning">
                                <i class="fas fa-user-plus text-white"></i>
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
                                <h6 class="text-info">Graduados</h6>
                                <h3 class="mb-0">
                                    <?php echo count(array_filter($estudiantes, fn($e) => $e['estado'] == 'graduado')); ?>
                                </h3>
                            </div>
                            <div class="icon-circle bg-info">
                                <i class="fas fa-graduation-cap text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Listado de estudiantes -->
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Estudiante</th>
                        <th>Carrera</th>
                        <th>Contacto</th>
                        <th>Semestre</th>
                        <th>Rendimiento</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($estudiantes as $estudiante): 
                        $edad = $estudiante['fecha_nacimiento'] ? 
                            date_diff(date_create($estudiante['fecha_nacimiento']), date_create('today'))->y : '';
                        $promedio = $estudiante['promedio_general'] ? number_format($estudiante['promedio_general'], 2) : 'N/A';
                        $estado_class = '';
                        switch ($estudiante['estado']) {
                            case 'activo': $estado_class = 'success'; break;
                            case 'inactivo': $estado_class = 'secondary'; break;
                            case 'egresado': $estado_class = 'info'; break;
                            case 'graduado': $estado_class = 'primary'; break;
                            case 'suspendido': $estado_class = 'danger'; break;
                        }
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo $estudiante['codigo_estudiante']; ?></strong>
                            <br>
                            <small class="text-muted">CI: <?php echo $estudiante['cedula']; ?></small>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm me-3">
                                    <div class="avatar-title bg-light rounded-circle">
                                        <i class="fas fa-user-graduate text-primary"></i>
                                    </div>
                                </div>
                                <div>
                                    <h6 class="mb-0"><?php echo $estudiante['nombres'] . ' ' . $estudiante['apellidos']; ?></h6>
                                    <small class="text-muted">
                                        <?php echo $edad ? $edad . ' años' : ''; ?> | 
                                        Ingreso: <?php echo date('d/m/Y', strtotime($estudiante['fecha_ingreso'])); ?>
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <small class="d-block"><?php echo $estudiante['carrera_nombre']; ?></small>
                            <small class="text-muted"><?php echo $estudiante['carrera_codigo']; ?></small>
                        </td>
                        <td>
                            <small class="d-block">
                                <i class="fas fa-envelope me-1"></i><?php echo $estudiante['email']; ?>
                            </small>
                            <?php if ($estudiante['telefono']): ?>
                            <small class="d-block">
                                <i class="fas fa-phone me-1"></i><?php echo $estudiante['telefono']; ?>
                            </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-info"><?php echo $estudiante['semestre_actual']; ?>° Semestre</span>
                        </td>
                        <td>
                            <div class="d-flex flex-column gap-1">
                                <div class="d-flex align-items-center">
                                    <span class="small me-2">Promedio:</span>
                                    <span class="badge bg-<?php echo ($promedio >= 16) ? 'success' : (($promedio >= 10) ? 'warning' : 'danger'); ?>">
                                        <?php echo $promedio; ?>
                                    </span>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: <?php echo min(100, ($estudiante['cursos_aprobados'] / max(1, $estudiante['total_matriculas'])) * 100); ?>%">
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?php echo $estudiante['cursos_aprobados']; ?>/<?php echo $estudiante['total_matriculas']; ?> cursos
                                </small>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $estado_class; ?>">
                                <?php echo ucfirst($estudiante['estado']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="estudiantes.php?action=editar&id=<?php echo $estudiante['id']; ?>" 
                                   class="btn btn-outline-primary" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="estudiante_perfil.php?id=<?php echo $estudiante['id']; ?>" 
                                   class="btn btn-outline-info" title="Ver perfil">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" 
                                            data-bs-toggle="dropdown" title="Más opciones">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="resetearPassword(<?php echo $estudiante['id']; ?>)">
                                                <i class="fas fa-key me-2"></i>Resetear Contraseña
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-danger" 
                                               href="estudiantes.php?action=eliminar&id=<?php echo $estudiante['id']; ?>" 
                                               onclick="return confirm('¿Eliminar este estudiante?')">
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
        
        <!-- Distribución por carrera -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Distribución de Estudiantes por Carrera</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <canvas id="chartDistribucionCarreras" height="150"></canvas>
                            </div>
                            <div class="col-md-4">
                                <div class="list-group">
                                    <?php
                                    $distribucion = [];
                                    foreach ($estudiantes as $estudiante) {
                                        $carrera = $estudiante['carrera_nombre'];
                                        $distribucion[$carrera] = ($distribucion[$carrera] ?? 0) + 1;
                                    }
                                    arsort($distribucion);
                                    
                                    foreach ($distribucion as $carrera => $cantidad):
                                        $porcentaje = round(($cantidad / count($estudiantes)) * 100, 1);
                                    ?>
                                    <div class="list-group-item border-0">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo $carrera; ?></h6>
                                            <small><?php echo $cantidad; ?> (<?php echo $porcentaje; ?>%)</small>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-primary" role="progressbar" 
                                                 style="width: <?php echo $porcentaje; ?>%"></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
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

<!-- Modal para importar estudiantes -->
<div class="modal fade" id="modalImportarEstudiantes" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Importar Estudiantes desde CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formImportarEstudiantes" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="archivo_csv" class="form-label">Archivo CSV</label>
                        <input type="file" class="form-control" id="archivo_csv" name="archivo_csv" accept=".csv" required>
                        <small class="text-muted">
                            <a href="plantilla_estudiantes.csv" class="d-block mt-1">
                                <i class="fas fa-download me-1"></i>Descargar plantilla
                            </a>
                            Formato: nombres,apellidos,cedula,fecha_nacimiento,genero,email,telefono,direccion,carrera_id,semestre_actual
                        </small>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="crear_usuarios" name="crear_usuarios" checked>
                                <label class="form-check-label" for="crear_usuarios">
                                    Crear usuarios automáticamente
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="enviar_correos" name="enviar_correos">
                                <label class="form-check-label" for="enviar_correos">
                                    Enviar emails de bienvenida
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Nota:</strong> 
                        <ul class="mb-0 mt-2">
                            <li>El archivo debe estar en formato UTF-8</li>
                            <li>La fecha de nacimiento debe estar en formato YYYY-MM-DD</li>
                            <li>Género debe ser: M, F u O</li>
                            <li>carrera_id debe ser un ID válido de carrera</li>
                        </ul>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-unexca" onclick="importarEstudiantes()">
                    <i class="fas fa-upload me-2"></i>Importar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function importarEstudiantes() {
    const form = document.getElementById('formImportarEstudiantes');
    const formData = new FormData(form);
    
    UNEXCA.Utils.showLoading('#modalImportarEstudiantes .modal-content', 'Importando estudiantes...');
    
    fetch('ajax/importar_estudiantes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        UNEXCA.Utils.hideLoading('#modalImportarEstudiantes .modal-content');
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Importación exitosa!',
                html: `Se importaron ${data.importados} estudiantes correctamente.<br>
                       ${data.errores} registros tuvieron errores.`,
                confirmButtonText: 'Continuar'
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error en importación',
                text: data.message
            });
        }
    })
    .catch(error => {
        UNEXCA.Utils.hideLoading('#modalImportarEstudiantes .modal-content');
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Ocurrió un error al importar los estudiantes'
        });
    });
}

function exportarEstudiantes() {
    const filtros = new URLSearchParams(window.location.search);
    
    UNEXCA.Utils.showNotification('Preparando exportación...', 'info');
    
    setTimeout(() => {
        const link = document.createElement('a');
        link.href = `ajax/exportar_estudiantes.php?${filtros.toString()}`;
        link.download = `estudiantes_${new Date().toISOString().split('T')[0]}.xlsx`;
        link.click();
        
        UNEXCA.Utils.showNotification('Exportación completada', 'success');
    }, 1000);
}

function resetearPassword(estudianteId) {
    Swal.fire({
        title: 'Resetear Contraseña',
        text: '¿Está seguro de resetear la contraseña de este estudiante?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, resetear',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`ajax/resetear_password.php?estudiante_id=${estudianteId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: '¡Contraseña reseteada!',
                        html: `La nueva contraseña es: <code>${data.nueva_password}</code><br>
                               <small>Recomiende al estudiante cambiarla inmediatamente.</small>`,
                        icon: 'success'
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

// Generar gráfico de distribución por carrera
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($estudiantes)): ?>
    const ctx = document.getElementById('chartDistribucionCarreras').getContext('2d');
    
    <?php
    // Preparar datos para el gráfico
    $carreras_distribucion = [];
    $cantidades_distribucion = [];
    $colores = ['#0056b3', '#28a745', '#dc3545', '#ffc107', '#17a2b8', '#6c757d', '#6610f2', '#e83e8c', '#20c997', '#fd7e14'];
    
    foreach ($distribucion as $carrera => $cantidad) {
        $carreras_distribucion[] = $carrera;
        $cantidades_distribucion[] = $cantidad;
    }
    ?>
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($carreras_distribucion); ?>,
            datasets: [{
                data: <?php echo json_encode($cantidades_distribucion); ?>,
                backgroundColor: <?php echo json_encode(array_slice($colores, 0, count($carreras_distribucion))); ?>,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 12,
                        padding: 15
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    // Validación de formulario
    const form = document.getElementById('formEstudiante');
    if (form) {
        form.addEventListener('submit', function(e) {
            const cedula = document.getElementById('cedula').value;
            const email = document.getElementById('email').value;
            
            // Validar cédula (ejemplo simple)
            if (cedula.length < 6) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Cédula inválida',
                    text: 'La cédula debe tener al menos 6 caracteres'
                });
                return;
            }
            
            // Validar email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Email inválido',
                    text: 'Por favor ingrese un email válido'
                });
                return;
            }
        });
    }
    
    // Inicializar DataTable
    /*$('.datatable').DataTable({
        destroy: true,
        language: { 
            url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
        },
        responsive: true,
        dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
        pageLength: 25,
        order: [[1, 'asc']],
        columnDefs: [
            { orderable: false, targets: -1 } // Deshabilitar orden en columna de acciones
        ]
    });*/
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

.avatar-title {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.border-left-primary { border-left-color: #0056b3 !important; }
.border-left-success { border-left-color: #28a745 !important; }
.border-left-warning { border-left-color: #ffc107 !important; }
.border-left-info { border-left-color: #17a2b8 !important; }
</style>

<?php
// Función para generar código de estudiante
function generarCodigoEstudiante($conn, $carrera_id) {
    // Obtener código de carrera
    $query = "SELECT codigo FROM carreras WHERE id = :carrera_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':carrera_id', $carrera_id);
    $stmt->execute();
    $carrera = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$carrera) {
        return 'EST-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    // Contar estudiantes en esta carrera
    $query = "SELECT COUNT(*) as total FROM estudiantes WHERE carrera_id = :carrera_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':carrera_id', $carrera_id);
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $codigo_carrera = substr($carrera['codigo'], 0, 3);
    $anio = date('y'); // Últimos 2 dígitos del año
    $secuencia = str_pad($total + 1, 4, '0', STR_PAD_LEFT);
    
    return strtoupper($codigo_carrera) . $anio . $secuencia;
}

include '../../includes/footer.php';
?>