<?php
// modules/calificaciones/tipos_evaluacion.php
require_once '../../config/database.php';
require_once '../../config/constants.php';

//verificamos el rol, si es docente o administrador (solo estos pueden realizar acciones en las evaluaciones de los estudiantes)

if (!in_array($_SESSION['rol'], ['docente', 'administrador'])) {
    header('Location: ../../index.php');
    exit();
}

$page_title = 'Tipos de Evaluación';

$db = new Database();
$conn = $db->getConnection();

$seccion_id = $_GET['seccion_id'] ?? 0;

// Validar que el docente tenga acceso a esta sección
if ($_SESSION['rol'] == 'docente') {
    $query = "SELECT id FROM secciones WHERE docente_id = (SELECT id FROM docentes WHERE usuario_id = :user_id) AND id = :seccion_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':seccion_id', $seccion_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        $_SESSION['message'] = 'No tiene permisos para acceder a esta sección';
        $_SESSION['message_type'] = 'danger';
        header('Location: index.php');
        exit();
    }
}

// Obtener información de la sección
$query = "SELECT s.*, c.nombre as curso_nombre, c.codigo as curso_codigo 
          FROM secciones s 
          JOIN cursos c ON s.curso_id = c.id 
          WHERE s.id = :seccion_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':seccion_id', $seccion_id);
$stmt->execute();
$seccion = $stmt->fetch(PDO::FETCH_ASSOC);

// Manejar acciones
$action = $_POST['action'] ?? '';
$id = $_POST['id'] ?? 0;

if ($action == 'guardar') {
    $nombre = sanitize($_POST['nombre']);
    $descripcion = sanitize($_POST['descripcion']);
    $peso = floatval($_POST['peso']);
    $orden = intval($_POST['orden']);
    
    // Validar que la suma de pesos no exceda 100%
    $query = "SELECT SUM(peso) as total_peso FROM tipos_evaluacion WHERE seccion_id = :seccion_id";
    if ($id > 0) {
        $query .= " AND id != :id";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':seccion_id', $seccion_id);
    if ($id > 0) {
        $stmt->bindParam(':id', $id);
    }
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_peso = $result['total_peso'] ?? 0;
    
    if (($total_peso + $peso) > 100) {
        $_SESSION['message'] = 'La suma de los pesos no puede exceder el 100%';
        $_SESSION['message_type'] = 'danger';
    } else {
        if ($id > 0) {
            // Actualizar
            $query = "UPDATE tipos_evaluacion SET 
                      nombre = :nombre,
                      descripcion = :descripcion,
                      peso = :peso,
                      orden = :orden
                      WHERE id = :id AND seccion_id = :seccion_id";
        } else {
            // Insertar
            $query = "INSERT INTO tipos_evaluacion (nombre, descripcion, peso, seccion_id, orden) 
                      VALUES (:nombre, :descripcion, :peso, :seccion_id, :orden)";
        }
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':peso', $peso);
        $stmt->bindParam(':seccion_id', $seccion_id);
        $stmt->bindParam(':orden', $orden);
        
        if ($id > 0) {
            $stmt->bindParam(':id', $id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['message'] = 'Tipo de evaluación guardado correctamente';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error al guardar el tipo de evaluación';
            $_SESSION['message_type'] = 'danger';
        }
    }
    
    header('Location: tipos_evaluacion.php?seccion_id=' . $seccion_id);
    exit();
}

if ($action == 'eliminar' && $id > 0) {
    $query = "DELETE FROM tipos_evaluacion WHERE id = :id AND seccion_id = :seccion_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':seccion_id', $seccion_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Tipo de evaluación eliminado correctamente';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Error al eliminar el tipo de evaluación';
        $_SESSION['message_type'] = 'danger';
    }
    
    header('Location: tipos_evaluacion.php?seccion_id=' . $seccion_id);
    exit();
}

// Obtener tipos de evaluación de la sección
$query = "SELECT * FROM tipos_evaluacion WHERE seccion_id = :seccion_id ORDER BY orden, id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':seccion_id', $seccion_id);
$stmt->execute();
$tipos_evaluacion = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        <div>
            <h5 class="mb-0">Tipos de Evaluación</h5>
            <small class="text-muted"><?php echo $seccion['curso_codigo'] . ' - ' . $seccion['curso_nombre']; ?></small>
        </div>
        <div>
            <a href="libro_calificaciones.php?seccion_id=<?php echo $seccion_id; ?>" class="btn btn-outline-unexca me-2">
                <i class="fas fa-arrow-left me-1"></i>Volver
            </a>
            <button type="button" class="btn btn-unexca" data-bs-toggle="modal" data-bs-target="#modalTipoEvaluacion">
                <i class="fas fa-plus me-1"></i>Nuevo Tipo
            </button>
        </div>
    </div>
    
    <div class="card-body">
        <!-- Resumen de pesos -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Total de pesos asignados:</strong> 
                            <?php 
                            $total_peso = array_sum(array_column($tipos_evaluacion, 'peso'));
                            echo $total_peso . '%';
                            ?>
                        </div>
                        <div>
                            <strong>Restante:</strong> <?php echo (100 - $total_peso) . '%'; ?>
                        </div>
                    </div>
                    <div class="progress mt-2" style="height: 10px;">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?php echo $total_peso; ?>%">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabla de tipos de evaluación -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th width="50">Orden</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Peso</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tipos_evaluacion)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4">
                            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No hay tipos de evaluación definidos</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($tipos_evaluacion as $tipo): ?>
                    <tr>
                        <td>
                            <span class="badge bg-primary"><?php echo $tipo['orden']; ?></span>
                        </td>
                        <td>
                            <strong><?php echo $tipo['nombre']; ?></strong>
                        </td>
                        <td>
                            <?php echo $tipo['descripcion'] ?: '<span class="text-muted">Sin descripción</span>'; ?>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: <?php echo $tipo['peso']; ?>%">
                                    </div>
                                </div>
                                <span class="fw-bold"><?php echo $tipo['peso']; ?>%</span>
                            </div>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary" 
                                        onclick="editarTipoEvaluacion(<?php echo $tipo['id']; ?>, 
                                                                     '<?php echo addslashes($tipo['nombre']); ?>',
                                                                     '<?php echo addslashes($tipo['descripcion']); ?>',
                                                                     <?php echo $tipo['peso']; ?>,
                                                                     <?php echo $tipo['orden']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="tipos_evaluacion.php?action=eliminar&id=<?php echo $tipo['id']; ?>&seccion_id=<?php echo $seccion_id; ?>" 
                                   class="btn btn-outline-danger confirm-delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para agregar/editar tipo de evaluación -->
<div class="modal fade" id="modalTipoEvaluacion" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">Nuevo Tipo de Evaluación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="guardar">
                    <input type="hidden" name="id" id="tipoId" value="0">
                    <input type="hidden" name="seccion_id" value="<?php echo $seccion_id; ?>">
                    
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="peso" class="form-label">Peso (%) *</label>
                            <input type="number" class="form-control" id="peso" name="peso" 
                                   min="0" max="100" step="0.01" required>
                            <small class="text-muted">Porcentaje que representa en la nota final</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="orden" class="form-label">Orden *</label>
                            <input type="number" class="form-control" id="orden" name="orden" 
                                   min="1" required>
                            <small class="text-muted">Orden de aparición en el libro</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-unexca">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editarTipoEvaluacion(id, nombre, descripcion, peso, orden) {
    document.getElementById('tipoId').value = id;
    document.getElementById('nombre').value = nombre;
    document.getElementById('descripcion').value = descripcion;
    document.getElementById('peso').value = peso;
    document.getElementById('orden').value = orden;
    
    document.getElementById('modalTitulo').textContent = 'Editar Tipo de Evaluación';
    
    var modal = new bootstrap.Modal(document.getElementById('modalTipoEvaluacion'));
    modal.show();
}
</script>

<?php include '../../includes/footer.php'; ?>