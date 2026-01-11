<?php
// modules/administrativo/docentes.php
require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SESSION['rol'] != 'administrador') {
    header('Location: ../../index.php');
    exit();
}

$page_title = 'Gestión de Docentes';

$db = new Database();   
$conn = $db->getConnection();

// Manejar acciones
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

// Crear/editar docente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_docente'])) {
    $nombres = sanitize($_POST['nombres']);
    $apellidos = sanitize($_POST['apellidos']);
    $cedula = sanitize($_POST['cedula']);
    $codigo_docente = sanitize($_POST['codigo_docente']);
    $titulo_academico = sanitize($_POST['titulo_academico']);
    $especialidad = sanitize($_POST['especialidad']);
    $email = sanitize($_POST['email']);
    $telefono = sanitize($_POST['telefono']);
    $fecha_contratacion = $_POST['fecha_contratacion'];
    $departamento_id = $_POST['departamento_id'] ?? null;
    $estado = $_POST['estado'] ?? 'activo';
    
    if ($id > 0) {
        // Actualizar docente existente
        $query = "UPDATE docentes SET 
                  nombres = :nombres,
                  apellidos = :apellidos,
                  cedula = :cedula,
                  codigo_docente = :codigo_docente,
                  titulo_academico = :titulo_academico,
                  especialidad = :especialidad,
                  email = :email,
                  telefono = :telefono,
                  fecha_contratacion = :fecha_contratacion,
                  departamento_id = :departamento_id,
                  estado = :estado
                  WHERE id = :id";
    } else {
        // Crear nuevo docente
        $query = "INSERT INTO docentes 
                  (nombres, apellidos, cedula, codigo_docente, titulo_academico, 
                   especialidad, email, telefono, fecha_contratacion, departamento_id, estado) 
                  VALUES 
                  (:nombres, :apellidos, :cedula, :codigo_docente, :titulo_academico,
                   :especialidad, :email, :telefono, :fecha_contratacion, :departamento_id, :estado)";
    }
    
    $stmt = $conn->prepare($query);
    $params = [
        ':nombres' => $nombres,
        ':apellidos' => $apellidos,
        ':cedula' => $cedula,
        ':codigo_docente' => $codigo_docente,
        ':titulo_academico' => $titulo_academico,
        ':especialidad' => $especialidad,
        ':email' => $email,
        ':telefono' => $telefono,
        ':fecha_contratacion' => $fecha_contratacion,
        ':departamento_id' => $departamento_id,
        ':estado' => $estado
    ];
    
    if ($id > 0) {
        $params[':id'] = $id;
    }
    
    if ($stmt->execute($params)) {
        $docente_id = $id > 0 ? $id : $conn->lastInsertId();
        
        // Si es nuevo, crear usuario asociado
        if ($id == 0) {
            $username = strtolower(substr($nombres, 0, 1) . $apellidos);
            $password = password_hash('Docente123', PASSWORD_DEFAULT);
            
            $query_user = "INSERT INTO usuarios (username, password, email, rol, estado) 
                          VALUES (:username, :password, :email, 'docente', 'activo')";
            $stmt_user = $conn->prepare($query_user);
            $stmt_user->bindParam(':username', $username);
            $stmt_user->bindParam(':password', $password);
            $stmt_user->bindParam(':email', $email);
            
            if ($stmt_user->execute()) {
                $usuario_id = $conn->lastInsertId();
                
                // Asociar usuario con docente
                $query_update = "UPDATE docentes SET usuario_id = :usuario_id WHERE id = :docente_id";
                $stmt_update = $conn->prepare($query_update);
                $stmt_update->bindParam(':usuario_id', $usuario_id);
                $stmt_update->bindParam(':docente_id', $docente_id);
                $stmt_update->execute();
            }
        }
        
        $_SESSION['message'] = 'Docente ' . ($id > 0 ? 'actualizado' : 'registrado') . ' exitosamente';
        $_SESSION['message_type'] = 'success';
        header('Location: docentes.php');
        exit();
    } else {
        $_SESSION['message'] = 'Error al guardar el docente';
        $_SESSION['message_type'] = 'danger';
    }
}

// Eliminar docente
if ($action == 'eliminar' && $id > 0) {
    // Verificar si tiene secciones asignadas
    $query = "SELECT COUNT(*) as total FROM secciones WHERE docente_id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $secciones_asignadas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($secciones_asignadas > 0) {
        $_SESSION['message'] = 'No se puede eliminar el docente porque tiene secciones asignadas';
        $_SESSION['message_type'] = 'danger';
    } else {
        $query = "DELETE FROM docentes WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = 'Docente eliminado exitosamente';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error al eliminar el docente';
            $_SESSION['message_type'] = 'danger';
        }
    }
    
    header('Location: docentes.php');
    exit();
}

// Filtros de búsqueda
$search = $_GET['search'] ?? '';
$departamento_id = $_GET['departamento_id'] ?? '';
$estado = $_GET['estado'] ?? '';

// Construir consulta
$query = "SELECT d.*, 
                 COUNT(DISTINCT s.id) as total_secciones,
                 COUNT(DISTINCT m.id) as total_estudiantes,
                 (SELECT COUNT(*) FROM calificaciones c 
                  JOIN tipos_evaluacion te ON c.tipo_evaluacion_id = te.id 
                  JOIN secciones s2 ON te.seccion_id = s2.id 
                  WHERE s2.docente_id = d.id) as total_calificaciones
          FROM docentes d 
          LEFT JOIN secciones s ON d.id = s.docente_id 
          LEFT JOIN matriculas m ON s.id = m.seccion_id 
          WHERE 1=1";

$params = [];

if (!empty($search)) {
    $query .= " AND (d.nombres LIKE :search OR d.apellidos LIKE :search OR d.codigo_docente LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($departamento_id)) {
    $query .= " AND d.departamento_id = :departamento_id";
    $params[':departamento_id'] = $departamento_id;
}

if (!empty($estado)) {
    $query .= " AND d.estado = :estado";
    $params[':estado'] = $estado;
}

$query .= " GROUP BY d.id ORDER BY d.apellidos, d.nombres";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$docentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener departamentos para filtro
$query = "SELECT * FROM departamentos WHERE estado = 'activo' ORDER BY nombre";
$stmt = $conn->query($query);
$departamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si estamos editando, obtener datos del docente
$docente_actual = null;
if ($action == 'editar' && $id > 0) {
    $query = "SELECT * FROM docentes WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $docente_actual = $stmt->fetch(PDO::FETCH_ASSOC);
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
        <h5 class="mb-0">Gestión de Docentes</h5>
        <div>
            <button type="button" class="btn btn-unexca" data-bs-toggle="modal" data-bs-target="#modalImportarDocentes">
                <i class="fas fa-file-import me-2"></i>Importar
            </button>
            <a href="docentes.php?action=nuevo" class="btn btn-unexca ms-2">
                <i class="fas fa-plus me-2"></i>Nuevo Docente
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <!-- Filtros -->
        <div class="row mb-4">
            <div class="col-md-12">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" placeholder="Buscar docente..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="departamento_id">
                            <option value="">Todos los departamentos</option>
                            <?php foreach ($departamentos as $dep): ?>
                            <option value="<?php echo $dep['id']; ?>" 
                                    <?php echo ($departamento_id == $dep['id']) ? 'selected' : ''; ?>>
                                <?php echo $dep['nombre']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="estado">
                            <option value="">Todos los estados</option>
                            <option value="activo" <?php echo ($estado == 'activo') ? 'selected' : ''; ?>>Activo</option>
                            <option value="inactivo" <?php echo ($estado == 'inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                            <option value="licencia" <?php echo ($estado == 'licencia') ? 'selected' : ''; ?>>Licencia</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-unexca w-100">
                            <i class="fas fa-filter me-1"></i>Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($action == 'nuevo' || $action == 'editar'): ?>
        <!-- Formulario de docente -->
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><?php echo $action == 'editar' ? 'Editar Docente' : 'Nuevo Docente'; ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="guardar_docente" value="1">
                            <?php if ($action == 'editar'): ?>
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nombres" class="form-label">Nombres *</label>
                                    <input type="text" class="form-control" id="nombres" name="nombres" 
                                           value="<?php echo $docente_actual['nombres'] ?? ''; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="apellidos" class="form-label">Apellidos *</label>
                                    <input type="text" class="form-control" id="apellidos" name="apellidos" 
                                           value="<?php echo $docente_actual['apellidos'] ?? ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="cedula" class="form-label">Cédula *</label>
                                    <input type="text" class="form-control" id="cedula" name="cedula" 
                                           value="<?php echo $docente_actual['cedula'] ?? ''; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="codigo_docente" class="form-label">Código Docente *</label>
                                    <input type="text" class="form-control" id="codigo_docente" name="codigo_docente" 
                                           value="<?php echo $docente_actual['codigo_docente'] ?? ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="titulo_academico" class="form-label">Título Académico *</label>
                                    <input type="text" class="form-control" id="titulo_academico" name="titulo_academico" 
                                           value="<?php echo $docente_actual['titulo_academico'] ?? ''; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="especialidad" class="form-label">Especialidad</label>
                                    <input type="text" class="form-control" id="especialidad" name="especialidad" 
                                           value="<?php echo $docente_actual['especialidad'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo $docente_actual['email'] ?? ''; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="telefono" class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" id="telefono" name="telefono" 
                                           value="<?php echo $docente_actual['telefono'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="fecha_contratacion" class="form-label">Fecha de Contratación *</label>
                                    <input type="date" class="form-control" id="fecha_contratacion" name="fecha_contratacion" 
                                           value="<?php echo $docente_actual['fecha_contratacion'] ?? date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="departamento_id" class="form-label">Departamento</label>
                                    <select class="form-select" id="departamento_id" name="departamento_id">
                                        <option value="">Seleccionar departamento</option>
                                        <?php foreach ($departamentos as $dep): ?>
                                        <option value="<?php echo $dep['id']; ?>" 
                                                <?php echo (($docente_actual['departamento_id'] ?? '') == $dep['id']) ? 'selected' : ''; ?>>
                                            <?php echo $dep['nombre']; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="estado" class="form-label">Estado *</label>
                                    <select class="form-select" id="estado" name="estado" required>
                                        <option value="activo" <?php echo (($docente_actual['estado'] ?? '') == 'activo') ? 'selected' : ''; ?>>Activo</option>
                                        <option value="inactivo" <?php echo (($docente_actual['estado'] ?? '') == 'inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                                        <option value="licencia" <?php echo (($docente_actual['estado'] ?? '') == 'licencia') ? 'selected' : ''; ?>>Licencia</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="docentes.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Cancelar
                                </a>
                                <button type="submit" class="btn btn-unexca">
                                    <i class="fas fa-save me-2"></i>Guardar Docente
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Listado de docentes -->
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Docente</th>
                        <th>Contacto</th>
                        <th>Título/Especialidad</th>
                        <th>Estadísticas</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($docentes as $docente): ?>
                    <tr>
                        <td>
                            <strong><?php echo $docente['codigo_docente']; ?></strong>
                            <br>
                            <small class="text-muted">CI: <?php echo $docente['cedula']; ?></small>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm me-3">
                                    <div class="avatar-title bg-light rounded-circle">
                                        <i class="fas fa-chalkboard-teacher text-primary"></i>
                                    </div>
                                </div>
                                <div>
                                    <h6 class="mb-0"><?php echo $docente['nombres'] . ' ' . $docente['apellidos']; ?></h6>
                                    <small class="text-muted">
                                        Contratado: <?php echo date('d/m/Y', strtotime($docente['fecha_contratacion'])); ?>
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <small class="d-block">
                                <i class="fas fa-envelope me-1"></i><?php echo $docente['email']; ?>
                            </small>
                            <?php if ($docente['telefono']): ?>
                            <small class="d-block">
                                <i class="fas fa-phone me-1"></i><?php echo $docente['telefono']; ?>
                            </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="d-block">
                                <strong><?php echo $docente['titulo_academico']; ?></strong>
                            </small>
                            <?php if ($docente['especialidad']): ?>
                            <small class="d-block text-muted">
                                <?php echo $docente['especialidad']; ?>
                            </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge bg-info" title="Secciones asignadas">
                                    <i class="fas fa-layer-group me-1"></i><?php echo $docente['total_secciones']; ?>
                                </span>
                                <span class="badge bg-success" title="Estudiantes">
                                    <i class="fas fa-users me-1"></i><?php echo $docente['total_estudiantes']; ?>
                                </span>
                                <span class="badge bg-warning" title="Calificaciones registradas">
                                    <i class="fas fa-edit me-1"></i><?php echo $docente['total_calificaciones']; ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <?php
                            $estado_class = '';
                            switch ($docente['estado']) {
                                case 'activo': $estado_class = 'success'; break;
                                case 'inactivo': $estado_class = 'secondary'; break;
                                case 'licencia': $estado_class = 'warning'; break;
                                default: $estado_class = 'info';
                            }
                            ?>
                            <span class="badge bg-<?php echo $estado_class; ?>">
                                <?php echo ucfirst($docente['estado']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="docentes.php?action=editar&id=<?php echo $docente['id']; ?>" 
                                   class="btn btn-outline-primary" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="docente_perfil.php?id=<?php echo $docente['id']; ?>" 
                                   class="btn btn-outline-info" title="Ver perfil">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="docentes.php?action=eliminar&id=<?php echo $docente['id']; ?>" 
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
        
        <!-- Resumen estadístico -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Resumen de Docentes</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3 mb-3">
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo count($docentes); ?></div>
                                    <div class="stat-label">Total Docentes</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stat-card">
                                    <div class="stat-value text-success">
                                        <?php echo count(array_filter($docentes, fn($d) => $d['estado'] == 'activo')); ?>
                                    </div>
                                    <div class="stat-label">Activos</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stat-card">
                                    <div class="stat-value text-warning">
                                        <?php echo count(array_filter($docentes, fn($d) => $d['estado'] == 'licencia')); ?>
                                    </div>
                                    <div class="stat-label">Licencia</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stat-card">
                                    <div class="stat-value text-secondary">
                                        <?php echo count(array_filter($docentes, fn($d) => $d['estado'] == 'inactivo')); ?>
                                    </div>
                                    <div class="stat-label">Inactivos</div>
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

<!-- Modal para importar docentes -->
<div class="modal fade" id="modalImportarDocentes" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Importar Docentes desde CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formImportarDocentes" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="archivo_csv" class="form-label">Archivo CSV</label>
                        <input type="file" class="form-control" id="archivo_csv" name="archivo_csv" accept=".csv" required>
                        <small class="text-muted">
                            Formato requerido: nombres,apellidos,cedula,codigo_docente,email,titulo_academico,especialidad,telefono
                        </small>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="crear_usuarios" name="crear_usuarios" checked>
                            <label class="form-check-label" for="crear_usuarios">
                                Crear usuarios automáticamente
                            </label>
                        </div>
                    </div>
                </form>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Nota:</strong> Se generarán contraseñas temporales para los nuevos usuarios.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-unexca" onclick="importarDocentes()">
                    <i class="fas fa-upload me-2"></i>Importar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function importarDocentes() {
    const form = document.getElementById('formImportarDocentes');
    const formData = new FormData(form);
    
    UNEXCA.Utils.showLoading('#modalImportarDocentes .modal-content', 'Importando docentes...');
    
    fetch('ajax/importar_docentes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        UNEXCA.Utils.hideLoading('#modalImportarDocentes .modal-content');
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Importación exitosa!',
                html: `Se importaron ${data.importados} docentes correctamente.<br>
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
        UNEXCA.Utils.hideLoading('#modalImportarDocentes .modal-content');
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Ocurrió un error al importar los docentes'
        });
    });
}

// Inicializar DataTable con opciones personalizadas
$(document).ready(function() {
    $('.datatable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
        },
        responsive: true,
        dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
        pageLength: 25,
        order: [[1, 'asc']]
    });
});
</script>

<style>
.stat-card {
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
    border: 1px solid #dee2e6;
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: #0056b3;
}

.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.avatar-title {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}
</style>

<?php include '../../includes/footer.php'; ?>