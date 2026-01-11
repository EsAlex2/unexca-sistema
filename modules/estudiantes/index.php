<?php
// modules/estudiantes/index.php
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

if ($action == 'eliminar' && $id > 0) {
    $query = "DELETE FROM estudiantes WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Estudiante eliminado correctamente';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Error al eliminar estudiante';
        $_SESSION['message_type'] = 'danger';
    }
    
    header('Location: index.php');
    exit();
}

// Búsqueda y filtros
$search = $_GET['search'] ?? '';
$carrera_id = $_GET['carrera_id'] ?? '';
$estado = $_GET['estado'] ?? '';

// Construir consulta
$query = "SELECT e.*, c.nombre as carrera, 
                 (SELECT COUNT(*) FROM matriculas m 
                  JOIN secciones s ON m.seccion_id = s.id 
                  WHERE m.estudiante_id = e.id AND m.estado = 'aprobado') as cursos_aprobados
          FROM estudiantes e 
          LEFT JOIN carreras c ON e.carrera_id = c.id 
          WHERE 1=1";

$params = [];

if (!empty($search)) {
    $query .= " AND (e.codigo_estudiante LIKE :search OR 
                     e.nombres LIKE :search OR 
                     e.apellidos LIKE :search OR 
                     e.cedula LIKE :search)";
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

$query .= " ORDER BY e.apellidos, e.nombres";

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
        <h5 class="mb-0">Listado de Estudiantes</h5>
        <a href="form.php?action=nuevo" class="btn btn-unexca">
            <i class="fas fa-plus me-2"></i>Nuevo Estudiante
        </a>
    </div>
    <div class="card-body">
        <!-- Filtros de búsqueda -->
        <div class="row mb-4">
            <div class="col-md-12">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="search" placeholder="Buscar..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select select2" name="carrera_id">
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
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-unexca w-100">
                            <i class="fas fa-search me-1"></i>Filtrar
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="index.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-redo me-1"></i>Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tabla de estudiantes -->
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre Completo</th>
                        <th>Cédula</th>
                        <th>Carrera</th>
                        <th>Semestre</th>
                        <th>Promedio</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($estudiantes as $estudiante): ?>
                    <tr>
                        <td>
                            <strong><?php echo $estudiante['codigo_estudiante']; ?></strong>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm me-3">
                                    <div class="avatar-title bg-light rounded-circle">
                                        <i class="fas fa-user text-primary"></i>
                                    </div>
                                </div>
                                <div>
                                    <h6 class="mb-0"><?php echo $estudiante['nombres'] . ' ' . $estudiante['apellidos']; ?></h6>
                                    <small class="text-muted"><?php echo $estudiante['email'] ?? 'Sin email'; ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?php echo $estudiante['cedula']; ?></td>
                        <td><?php echo $estudiante['carrera'] ?? 'No asignada'; ?></td>
                        <td>
                            <span class="badge bg-info"><?php echo $estudiante['semestre_actual']; ?>°</span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: <?php echo min(100, ($estudiante['promedio_general'] / 20) * 100); ?>%">
                                    </div>
                                </div>
                                <span class="fw-bold"><?php echo number_format($estudiante['promedio_general'], 2); ?></span>
                            </div>
                        </td>
                        <td>
                            <?php
                            $estado_class = '';
                            switch ($estudiante['estado']) {
                                case 'activo': $estado_class = 'success'; break;
                                case 'inactivo': $estado_class = 'secondary'; break;
                                case 'egresado': $estado_class = 'primary'; break;
                                default: $estado_class = 'warning';
                            }
                            ?>
                            <span class="badge bg-<?php echo $estado_class; ?>">
                                <?php echo ucfirst($estudiante['estado']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="perfil.php?id=<?php echo $estudiante['id']; ?>" 
                                   class="btn btn-sm btn-outline-unexca" title="Ver perfil">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="form.php?action=editar&id=<?php echo $estudiante['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="index.php?action=eliminar&id=<?php echo $estudiante['id']; ?>" 
                                   class="btn btn-sm btn-outline-danger confirm-delete" title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>