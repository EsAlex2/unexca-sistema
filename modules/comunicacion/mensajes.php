<?php
// modules/comunicacion/mensajes.php
require_once '../../config/database.php';
require_once '../../config/constants.php';

if (!isset($_SESSION['logged_in'])) {
    header('Location: ../../index.php');
    exit();
}

$page_title = 'Mensajería';

$db = new Database();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];
$rol = $_SESSION['rol'];

// Obtener parámetros
$action = $_GET['action'] ?? '';
$mensaje_id = $_GET['id'] ?? 0;
$destinatario_id = $_GET['destinatario'] ?? 0;

// Enviar mensaje
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enviar_mensaje'])) {
    $destinatario_id = $_POST['destinatario_id'];
    $asunto = sanitize($_POST['asunto']);
    $contenido = sanitize($_POST['contenido']);
    
    if (empty($destinatario_id) || empty($asunto) || empty($contenido)) {
        $_SESSION['message'] = 'Todos los campos son obligatorios';
        $_SESSION['message_type'] = 'danger';
    } else {
        $query = "INSERT INTO mensajes (remitente_id, destinatario_id, asunto, contenido) 
                  VALUES (:remitente_id, :destinatario_id, :asunto, :contenido)";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':remitente_id', $user_id);
        $stmt->bindParam(':destinatario_id', $destinatario_id);
        $stmt->bindParam(':asunto', $asunto);
        $stmt->bindParam(':contenido', $contenido);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = 'Mensaje enviado exitosamente';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error al enviar el mensaje';
            $_SESSION['message_type'] = 'danger';
        }
    }
    
    header('Location: mensajes.php');
    exit();
}

// Marcar como leído
if ($action == 'marcar_leido' && $mensaje_id > 0) {
    $query = "UPDATE mensajes SET leido = 1 
              WHERE id = :id AND destinatario_id = :destinatario_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $mensaje_id);
    $stmt->bindParam(':destinatario_id', $user_id);
    $stmt->execute();
    
    header('Location: mensajes.php?action=ver&id=' . $mensaje_id);
    exit();
}

// Eliminar mensaje
if ($action == 'eliminar' && $mensaje_id > 0) {
    $query = "DELETE FROM mensajes 
              WHERE id = :id AND (remitente_id = :user_id OR destinatario_id = :user_id)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $mensaje_id);
    $stmt->bindParam(':user_id', $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Mensaje eliminado';
        $_SESSION['message_type'] = 'success';
    }
    
    header('Location: mensajes.php');
    exit();
}

// Obtener usuarios para el selector
$usuarios = [];
if ($rol == 'administrador') {
    $query = "SELECT u.id, u.username, u.email, u.rol, 
                     COALESCE(e.nombres, d.nombres, 'Administrador') as nombre,
                     COALESCE(e.apellidos, d.apellidos, '') as apellido
              FROM usuarios u 
              LEFT JOIN estudiantes e ON u.id = e.usuario_id 
              LEFT JOIN docentes d ON u.id = d.usuario_id 
              WHERE u.id != :user_id 
              AND u.estado = 'activo'
              ORDER BY u.rol, nombre, apellido";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($rol == 'docente') {
    // Docentes pueden enviar a estudiantes de sus cursos y otros docentes
    $query = "SELECT DISTINCT u.id, u.username, u.email, u.rol, 
                     COALESCE(e.nombres, d.nombres) as nombre,
                     COALESCE(e.apellidos, d.apellidos) as apellido
              FROM usuarios u 
              LEFT JOIN estudiantes e ON u.id = e.usuario_id 
              LEFT JOIN docentes d ON u.id = d.usuario_id 
              WHERE u.id != :user_id 
              AND u.estado = 'activo'
              AND (
                  u.rol = 'docente' 
                  OR u.id IN (
                      SELECT e.usuario_id 
                      FROM estudiantes e 
                      JOIN matriculas m ON e.id = m.estudiante_id 
                      JOIN secciones s ON m.seccion_id = s.id 
                      WHERE s.docente_id = (SELECT id FROM docentes WHERE usuario_id = :user_id)
                  )
              )
              ORDER BY u.rol, nombre, apellido";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($rol == 'estudiante') {
    // Estudiantes pueden enviar a sus profesores y administradores
    $query = "SELECT DISTINCT u.id, u.username, u.email, u.rol, 
                     COALESCE(d.nombres, 'Administrador') as nombre,
                     COALESCE(d.apellidos, '') as apellido
              FROM usuarios u 
              LEFT JOIN docentes d ON u.id = d.usuario_id 
              WHERE u.id != :user_id 
              AND u.estado = 'activo'
              AND (
                  u.rol = 'administrador' 
                  OR u.id IN (
                      SELECT d.usuario_id 
                      FROM docentes d 
                      JOIN secciones s ON d.id = s.docente_id 
                      JOIN matriculas m ON s.id = m.seccion_id 
                      JOIN estudiantes e ON m.estudiante_id = e.id 
                      WHERE e.usuario_id = :user_id
                  )
              )
              ORDER BY u.rol, nombre, apellido";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener bandeja de entrada
$query = "SELECT m.*, 
                 u1.username as remitente_username,
                 u1.email as remitente_email,
                 COALESCE(e1.nombres, d1.nombres, 'Administrador') as remitente_nombre,
                 COALESCE(e1.apellidos, d1.apellidos, '') as remitente_apellido,
                 u1.rol as remitente_rol,
                 u2.username as destinatario_username,
                 u2.email as destinatario_email,
                 COALESCE(e2.nombres, d2.nombres, 'Administrador') as destinatario_nombre,
                 COALESCE(e2.apellidos, d2.apellidos, '') as destinatario_apellido,
                 u2.rol as destinatario_rol
          FROM mensajes m 
          JOIN usuarios u1 ON m.remitente_id = u1.id 
          JOIN usuarios u2 ON m.destinatario_id = u2.id 
          LEFT JOIN estudiantes e1 ON u1.id = e1.usuario_id 
          LEFT JOIN docentes d1 ON u1.id = d1.usuario_id 
          LEFT JOIN estudiantes e2 ON u2.id = e2.usuario_id 
          LEFT JOIN docentes d2 ON u2.id = d2.usuario_id 
          WHERE m.destinatario_id = :user_id 
          ORDER BY m.fecha_envio DESC";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$bandeja_entrada = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener mensajes enviados
$query = "SELECT m.*, 
                 u1.username as remitente_username,
                 u1.email as remitente_email,
                 COALESCE(e1.nombres, d1.nombres, 'Administrador') as remitente_nombre,
                 COALESCE(e1.apellidos, d1.apellidos, '') as remitente_apellido,
                 u1.rol as remitente_rol,
                 u2.username as destinatario_username,
                 u2.email as destinatario_email,
                 COALESCE(e2.nombres, d2.nombres, 'Administrador') as destinatario_nombre,
                 COALESCE(e2.apellidos, d2.apellidos, '') as destinatario_apellido,
                 u2.rol as destinatario_rol
          FROM mensajes m 
          JOIN usuarios u1 ON m.remitente_id = u1.id 
          JOIN usuarios u2 ON m.destinatario_id = u2.id 
          LEFT JOIN estudiantes e1 ON u1.id = e1.usuario_id 
          LEFT JOIN docentes d1 ON u1.id = d1.usuario_id 
          LEFT JOIN estudiantes e2 ON u2.id = e2.usuario_id 
          LEFT JOIN docentes d2 ON u2.id = d2.usuario_id 
          WHERE m.remitente_id = :user_id 
          ORDER BY m.fecha_envio DESC";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$mensajes_enviados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar mensajes no leídos
$query = "SELECT COUNT(*) as no_leidos 
          FROM mensajes 
          WHERE destinatario_id = :user_id 
          AND leido = 0";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$no_leidos = $stmt->fetch(PDO::FETCH_ASSOC)['no_leidos'];

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

<div class="row">
    <!-- Sidebar de mensajes -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Bandeja</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <a href="mensajes.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center 
                        <?php echo ($action == '' || $action == 'ver') ? 'active' : ''; ?>">
                        <span>
                            <i class="fas fa-inbox me-2"></i>Bandeja de entrada
                        </span>
                        <?php if ($no_leidos > 0): ?>
                        <span class="badge bg-danger rounded-pill"><?php echo $no_leidos; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="mensajes.php?action=enviados" class="list-group-item list-group-item-action 
                        <?php echo ($action == 'enviados') ? 'active' : ''; ?>">
                        <i class="fas fa-paper-plane me-2"></i>Mensajes enviados
                    </a>
                    <a href="mensajes.php?action=nuevo" class="list-group-item list-group-item-action 
                        <?php echo ($action == 'nuevo') ? 'active' : ''; ?>">
                        <i class="fas fa-edit me-2"></i>Nuevo mensaje
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Contactos frecuentes -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">Contactos</h6>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php 
                    $contactos_limitados = array_slice($usuarios, 0, 5);
                    foreach ($contactos_limitados as $contacto): 
                        $nombre_completo = $contacto['nombre'] . ' ' . $contacto['apellido'];
                        $rol_texto = ucfirst($contacto['rol']);
                        $rol_color = '';
                        switch ($contacto['rol']) {
                            case 'administrador': $rol_color = 'danger'; break;
                            case 'docente': $rol_color = 'primary'; break;
                            case 'estudiante': $rol_color = 'success'; break;
                            default: $rol_color = 'secondary';
                        }
                    ?>
                    <a href="mensajes.php?action=nuevo&destinatario=<?php echo $contacto['id']; ?>" 
                       class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0"><?php echo $nombre_completo; ?></h6>
                                <small class="text-muted"><?php echo $contacto['email']; ?></small>
                            </div>
                            <span class="badge bg-<?php echo $rol_color; ?>"><?php echo $rol_texto; ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    
                    <?php if (count($usuarios) > 5): ?>
                    <a href="mensajes.php?action=nuevo" class="list-group-item list-group-item-action text-center">
                        <small>Ver todos los contactos...</small>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido principal -->
    <div class="col-lg-8 mb-4">
        <?php if ($action == 'nuevo' || ($action == '' && $destinatario_id > 0)): ?>
        <!-- Formulario nuevo mensaje -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Nuevo Mensaje</h5>
                <a href="mensajes.php" class="btn btn-sm btn-outline-unexca">
                    <i class="fas fa-arrow-left me-1"></i>Volver
                </a>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="destinatario_id" class="form-label">Destinatario *</label>
                        <select class="form-select select2" id="destinatario_id" name="destinatario_id" required>
                            <option value="">Seleccionar destinatario...</option>
                            <?php foreach ($usuarios as $usuario): 
                                $nombre_completo = $usuario['nombre'] . ' ' . $usuario['apellido'] . 
                                                  ' (' . $usuario['email'] . ')';
                            ?>
                            <option value="<?php echo $usuario['id']; ?>" 
                                    <?php echo ($destinatario_id == $usuario['id']) ? 'selected' : ''; ?>>
                                <?php echo $nombre_completo; ?> - <?php echo ucfirst($usuario['rol']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="asunto" class="form-label">Asunto *</label>
                        <input type="text" class="form-control" id="asunto" name="asunto" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="contenido" class="form-label">Mensaje *</label>
                        <textarea class="form-control" id="contenido" name="contenido" rows="8" required></textarea>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="reset" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="submit" name="enviar_mensaje" class="btn btn-unexca">
                            <i class="fas fa-paper-plane me-2"></i>Enviar Mensaje
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($action == 'ver' && $mensaje_id > 0): ?>
        <!-- Ver mensaje -->
        <?php
        // Obtener mensaje específico
        $query = "SELECT m.*, 
                         u1.username as remitente_username,
                         u1.email as remitente_email,
                         COALESCE(e1.nombres, d1.nombres, 'Administrador') as remitente_nombre,
                         COALESCE(e1.apellidos, d1.apellidos, '') as remitente_apellido,
                         u1.rol as remitente_rol,
                         u2.username as destinatario_username,
                         u2.email as destinatario_email,
                         COALESCE(e2.nombres, d2.nombres, 'Administrador') as destinatario_nombre,
                         COALESCE(e2.apellidos, d2.apellidos, '') as destinatario_apellido,
                         u2.rol as destinatario_rol
                  FROM mensajes m 
                  JOIN usuarios u1 ON m.remitente_id = u1.id 
                  JOIN usuarios u2 ON m.destinatario_id = u2.id 
                  LEFT JOIN estudiantes e1 ON u1.id = e1.usuario_id 
                  LEFT JOIN docentes d1 ON u1.id = d1.usuario_id 
                  LEFT JOIN estudiantes e2 ON u2.id = e2.usuario_id 
                  LEFT JOIN docentes d2 ON u2.id = d2.usuario_id 
                  WHERE m.id = :mensaje_id 
                  AND (m.remitente_id = :user_id OR m.destinatario_id = :user_id)";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':mensaje_id', $mensaje_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $mensaje = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mensaje && $mensaje['destinatario_id'] == $user_id && !$mensaje['leido']) {
            // Marcar como leído
            $query = "UPDATE mensajes SET leido = 1 WHERE id = :mensaje_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':mensaje_id', $mensaje_id);
            $stmt->execute();
        }
        ?>
        
        <?php if ($mensaje): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo $mensaje['asunto']; ?></h5>
                <div>
                    <a href="mensajes.php?action=nuevo&destinatario=<?php echo $mensaje['remitente_id']; ?>" 
                       class="btn btn-sm btn-outline-unexca me-2">
                        <i class="fas fa-reply me-1"></i>Responder
                    </a>
                    <a href="mensajes.php?action=eliminar&id=<?php echo $mensaje['id']; ?>" 
                       class="btn btn-sm btn-outline-danger confirm-delete">
                        <i class="fas fa-trash me-1"></i>Eliminar
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted">De:</small>
                            <p class="mb-1">
                                <strong><?php echo $mensaje['remitente_nombre'] . ' ' . $mensaje['remitente_apellido']; ?></strong>
                                <span class="badge bg-<?php echo $mensaje['remitente_rol'] == 'administrador' ? 'danger' : 
                                                        ($mensaje['remitente_rol'] == 'docente' ? 'primary' : 'success'); ?> ms-2">
                                    <?php echo ucfirst($mensaje['remitente_rol']); ?>
                                </span>
                            </p>
                            <p class="mb-0 text-muted">
                                <?php echo $mensaje['remitente_email']; ?>
                            </p>
                        </div>
                        <div class="col-md-6 text-end">
                            <small class="text-muted">Para:</small>
                            <p class="mb-1">
                                <strong><?php echo $mensaje['destinatario_nombre'] . ' ' . $mensaje['destinatario_apellido']; ?></strong>
                                <span class="badge bg-<?php echo $mensaje['destinatario_rol'] == 'administrador' ? 'danger' : 
                                                        ($mensaje['destinatario_rol'] == 'docente' ? 'primary' : 'success'); ?> ms-2">
                                    <?php echo ucfirst($mensaje['destinatario_rol']); ?>
                                </span>
                            </p>
                            <p class="mb-0 text-muted">
                                <small>Enviado: <?php echo date('d/m/Y H:i', strtotime($mensaje['fecha_envio'])); ?></small>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="border-top pt-4">
                    <div style="white-space: pre-wrap; font-size: 1.1rem; line-height: 1.6;">
                        <?php echo htmlspecialchars_decode($mensaje['contenido']); ?>
                    </div>
                </div>
                
                <?php if ($mensaje['remitente_id'] == $user_id): ?>
                <div class="mt-4 pt-3 border-top">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Estado: 
                        <?php if ($mensaje['leido']): ?>
                        <span class="text-success">Leído</span> - 
                        <?php echo date('d/m/Y H:i', strtotime($mensaje['fecha_envio'] . ' + 1 hour')); ?>
                        <?php else: ?>
                        <span class="text-warning">No leído</span>
                        <?php endif; ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Mensaje no encontrado o no tiene permiso para verlo.
        </div>
        <?php endif; ?>
        
        <?php elseif ($action == 'enviados'): ?>
        <!-- Mensajes enviados -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Mensajes Enviados</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($mensajes_enviados)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-paper-plane fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No has enviado ningún mensaje</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($mensajes_enviados as $mensaje): ?>
                    <a href="mensajes.php?action=ver&id=<?php echo $mensaje['id']; ?>" 
                       class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between align-items-start">
                            <div class="flex-grow-1 me-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-1"><?php echo $mensaje['asunto']; ?></h6>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y', strtotime($mensaje['fecha_envio'])); ?>
                                    </small>
                                </div>
                                <p class="mb-1 text-truncate">
                                    <?php echo substr(strip_tags($mensaje['contenido']), 0, 100); ?>...
                                </p>
                                <small class="text-muted">
                                    Para: <?php echo $mensaje['destinatario_nombre'] . ' ' . $mensaje['destinatario_apellido']; ?>
                                    <span class="badge bg-<?php echo $mensaje['destinatario_rol'] == 'administrador' ? 'danger' : 
                                                            ($mensaje['destinatario_rol'] == 'docente' ? 'primary' : 'success'); ?> ms-2">
                                        <?php echo ucfirst($mensaje['destinatario_rol']); ?>
                                    </span>
                                </small>
                            </div>
                            <div class="text-end">
                                <?php if ($mensaje['leido']): ?>
                                <span class="badge bg-success" title="Leído">
                                    <i class="fas fa-check"></i>
                                </span>
                                <?php else: ?>
                                <span class="badge bg-warning" title="No leído">
                                    <i class="fas fa-clock"></i>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Bandeja de entrada -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Bandeja de Entrada</h5>
                <a href="mensajes.php?action=nuevo" class="btn btn-unexca btn-sm">
                    <i class="fas fa-edit me-1"></i>Nuevo Mensaje
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($bandeja_entrada)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No tienes mensajes</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($bandeja_entrada as $mensaje): ?>
                    <a href="mensajes.php?action=ver&id=<?php echo $mensaje['id']; ?>" 
                       class="list-group-item list-group-item-action <?php echo (!$mensaje['leido']) ? 'list-group-item-warning' : ''; ?>">
                        <div class="d-flex w-100 justify-content-between align-items-start">
                            <div class="flex-grow-1 me-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-1">
                                        <?php if (!$mensaje['leido']): ?>
                                        <span class="badge bg-primary me-2">Nuevo</span>
                                        <?php endif; ?>
                                        <?php echo $mensaje['asunto']; ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y', strtotime($mensaje['fecha_envio'])); ?>
                                    </small>
                                </div>
                                <p class="mb-1 text-truncate">
                                    <?php echo substr(strip_tags($mensaje['contenido']), 0, 100); ?>...
                                </p>
                                <small class="text-muted">
                                    De: <?php echo $mensaje['remitente_nombre'] . ' ' . $mensaje['remitente_apellido']; ?>
                                    <span class="badge bg-<?php echo $mensaje['remitente_rol'] == 'administrador' ? 'danger' : 
                                                            ($mensaje['remitente_rol'] == 'docente' ? 'primary' : 'success'); ?> ms-2">
                                        <?php echo ucfirst($mensaje['remitente_rol']); ?>
                                    </span>
                                </small>
                            </div>
                            <div>
                                <i class="fas fa-chevron-right text-muted"></i>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Inicializar Select2
$(document).ready(function() {
    $('.select2').select2({
        theme: 'bootstrap-5',
        placeholder: 'Seleccionar destinatario...',
        allowClear: true
    });
    
    // Auto-expand textarea
    const textarea = document.getElementById('contenido');
    if (textarea) {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>