<?php
// modules/estudiantes/matricula.php
require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SESSION['rol'] != 'estudiante') {
    header('Location: ../../index.php');
    exit();
}

$page_title = 'Matrícula de Cursos';

$db = new Database();
$conn = $db->getConnection();

// Obtener información del estudiante
$query = "SELECT e.*, c.nombre as carrera, c.creditos_totales, 
                 (SELECT SUM(cu.creditos) 
                  FROM matriculas m 
                  JOIN secciones s ON m.seccion_id = s.id 
                  JOIN cursos cu ON s.curso_id = cu.id 
                  WHERE m.estudiante_id = e.id 
                  AND m.estado = 'matriculado') as creditos_matriculados
          FROM estudiantes e 
          LEFT JOIN carreras c ON e.carrera_id = c.id 
          WHERE e.usuario_id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

$estudiante_id = $estudiante['id'];

// Obtener período académico actual
$periodo_actual = date('Y') . '-' . (date('n') <= 6 ? '1' : '2'); // Ej: 2024-1

// Manejar matrícula
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['matricular'])) {
        $secciones_seleccionadas = $_POST['secciones'] ?? [];
        
        if (empty($secciones_seleccionadas)) {
            $_SESSION['message'] = 'Debe seleccionar al menos una sección';
            $_SESSION['message_type'] = 'danger';
        } else {
            $conn->beginTransaction();
            $success = true;
            
            try {
                foreach ($secciones_seleccionadas as $seccion_id) {
                    // Verificar cupo disponible
                    $query = "SELECT cupo_maximo, cupo_actual FROM secciones WHERE id = :seccion_id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':seccion_id', $seccion_id);
                    $stmt->execute();
                    $seccion = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($seccion['cupo_actual'] >= $seccion['cupo_maximo']) {
                        throw new Exception('La sección seleccionada ya está llena');
                    }
                    
                    // Verificar que no esté ya matriculado
                    $query = "SELECT id FROM matriculas 
                              WHERE estudiante_id = :estudiante_id 
                              AND seccion_id = :seccion_id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':estudiante_id', $estudiante_id);
                    $stmt->bindParam(':seccion_id', $seccion_id);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        throw new Exception('Ya está matriculado en una de las secciones seleccionadas');
                    }
                    
                    // Verificar prerrequisitos
                    $query = "SELECT c.prerequisito_id 
                              FROM secciones s 
                              JOIN cursos c ON s.curso_id = c.id 
                              WHERE s.id = :seccion_id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':seccion_id', $seccion_id);
                    $stmt->execute();
                    $curso = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($curso['prerequisito_id']) {
                        $query = "SELECT 1 FROM matriculas m 
                                  JOIN secciones s ON m.seccion_id = s.id 
                                  WHERE m.estudiante_id = :estudiante_id 
                                  AND s.curso_id = :prerequisito_id 
                                  AND m.estado = 'aprobado'";
                        $stmt = $conn->prepare($query);
                        $stmt->bindParam(':estudiante_id', $estudiante_id);
                        $stmt->bindParam(':prerequisito_id', $curso['prerequisito_id']);
                        $stmt->execute();
                        
                        if ($stmt->rowCount() == 0) {
                            throw new Exception('No cumple con los prerrequisitos para una de las materias');
                        }
                    }
                    
                    // Realizar matrícula
                    $query = "INSERT INTO matriculas (estudiante_id, seccion_id, estado) 
                              VALUES (:estudiante_id, :seccion_id, 'matriculado')";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':estudiante_id', $estudiante_id);
                    $stmt->bindParam(':seccion_id', $seccion_id);
                    $stmt->execute();
                    
                    // Actualizar cupo de la sección
                    $query = "UPDATE secciones SET cupo_actual = cupo_actual + 1 WHERE id = :seccion_id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':seccion_id', $seccion_id);
                    $stmt->execute();
                }
                
                $conn->commit();
                $_SESSION['message'] = 'Matrícula realizada exitosamente';
                $_SESSION['message_type'] = 'success';
                
            } catch (Exception $e) {
                $conn->rollBack();
                $_SESSION['message'] = 'Error en la matrícula: ' . $e->getMessage();
                $_SESSION['message_type'] = 'danger';
                $success = false;
            }
        }
        
        header('Location: matricula.php');
        exit();
    }
    
    if (isset($_POST['retirar'])) {
        $matricula_id = $_POST['matricula_id'];
        
        $query = "DELETE FROM matriculas WHERE id = :matricula_id AND estudiante_id = :estudiante_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':matricula_id', $matricula_id);
        $stmt->bindParam(':estudiante_id', $estudiante_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = 'Curso retirado exitosamente';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error al retirar el curso';
            $_SESSION['message_type'] = 'danger';
        }
        
        header('Location: matricula.php');
        exit();
    }
}

// Obtener secciones disponibles para matrícula
$query = "SELECT s.*, c.nombre as curso_nombre, c.codigo as curso_codigo, 
                 c.creditos, c.prerequisito_id,
                 d.nombres as docente_nombre, d.apellidos as docente_apellido,
                 (SELECT nombre FROM cursos WHERE id = c.prerequisito_id) as prerequisito_nombre
          FROM secciones s 
          JOIN cursos c ON s.curso_id = c.id 
          JOIN docentes d ON s.docente_id = d.id 
          WHERE s.periodo_academico = :periodo 
          AND s.estado = 'abierta'
          AND s.cupo_actual < s.cupo_maximo
          AND c.id IN (
              SELECT curso_id FROM cursos 
              WHERE carrera_id = :carrera_id 
              AND semestre <= :semestre_actual + 1
          )
          AND s.id NOT IN (
              SELECT seccion_id FROM matriculas 
              WHERE estudiante_id = :estudiante_id
          )
          ORDER BY c.semestre, c.nombre";
$stmt = $conn->prepare($query);
$stmt->bindParam(':periodo', $periodo_actual);
$stmt->bindParam(':carrera_id', $estudiante['carrera_id']);
$stmt->bindParam(':semestre_actual', $estudiante['semestre_actual']);
$stmt->bindParam(':estudiante_id', $estudiante_id);
$stmt->execute();
$secciones_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener cursos ya matriculados
$query = "SELECT m.*, s.codigo_seccion, s.horario, s.aula,
                 c.nombre as curso_nombre, c.codigo as curso_codigo, c.creditos,
                 d.nombres as docente_nombre, d.apellidos as docente_apellido
          FROM matriculas m 
          JOIN secciones s ON m.seccion_id = s.id 
          JOIN cursos c ON s.curso_id = c.id 
          JOIN docentes d ON s.docente_id = d.id 
          WHERE m.estudiante_id = :estudiante_id 
          AND s.periodo_academico = :periodo
          AND m.estado = 'matriculado'
          ORDER BY c.nombre";
$stmt = $conn->prepare($query);
$stmt->bindParam(':estudiante_id', $estudiante_id);
$stmt->bindParam(':periodo', $periodo_actual);
$stmt->execute();
$cursos_matriculados = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-2">Período Académico <?php echo $periodo_actual; ?></h5>
                        <div class="row">
                            <div class="col-md-4">
                                <small class="text-muted d-block">Carrera</small>
                                <strong><?php echo "Ingenieria en Informatica"; ?></strong>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted d-block">Semestre Actual</small>
                                <strong><?php echo $estudiante['semestre_actual']; ?>° Semestre</strong>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted d-block">Créditos Matriculados</small>
                                <strong><?php echo $estudiante['creditos_matriculados'] ?? 0; ?> / <?php echo $estudiante['creditos_totales'] ?? 180; ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="bg-light p-3 rounded">
                            <small class="text-muted d-block">Estado de Matrícula</small>
                            <span class="badge bg-success fs-6">Activa</span>
                            <br>
                            <small class="text-muted">Hasta: <?php echo date('d/m/Y', strtotime('+3 months')); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Cursos ya matriculados -->
    <div class="col-lg-5 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Cursos Matriculados</h5>
            </div>
            <div class="card-body">
                <?php if (empty($cursos_matriculados)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No tienes cursos matriculados para este período</p>
                </div>
                <?php else: ?>
                <div class="list-group">
                    <?php 
                    $total_creditos = 0;
                    foreach ($cursos_matriculados as $curso): 
                        $total_creditos += $curso['creditos'];
                    ?>
                    <div class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="mb-1"><?php echo $curso['curso_nombre']; ?></h6>
                                <small class="text-muted"><?php echo $curso['curso_codigo']; ?> - Sección <?php echo $curso['codigo_seccion']; ?></small>
                            </div>
                            <span class="badge bg-unexca"><?php echo $curso['creditos']; ?> créditos</span>
                        </div>
                        
                        <div class="mb-2">
                            <small class="text-muted d-block">
                                <i class="fas fa-user-tie me-1"></i>
                                <?php echo $curso['docente_nombre'] . ' ' . $curso['docente_apellido']; ?>
                            </small>
                            <small class="text-muted d-block">
                                <i class="fas fa-clock me-1"></i><?php echo $curso['horario']; ?>
                            </small>
                            <small class="text-muted d-block">
                                <i class="fas fa-door-open me-1"></i><?php echo $curso['aula']; ?>
                            </small>
                        </div>
                        
                        <form method="POST" action="" class="mt-2">
                            <input type="hidden" name="matricula_id" value="<?php echo $curso['id']; ?>">
                            <button type="submit" name="retirar" class="btn btn-sm btn-outline-danger w-100"
                                    onclick="return confirm('¿Está seguro de retirar este curso?')">
                                <i class="fas fa-times me-1"></i>Retirar Curso
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-4 pt-3 border-top">
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted">Total de cursos:</small>
                            <h5><?php echo count($cursos_matriculados); ?></h5>
                        </div>
                        <div class="col-6 text-end">
                            <small class="text-muted">Total créditos:</small>
                            <h5 class="text-success"><?php echo $total_creditos; ?></h5>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Secciones disponibles -->
    <div class="col-lg-7 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Secciones Disponibles</h5>
            </div>
            <div class="card-body">
                <?php if (empty($secciones_disponibles)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No hay secciones disponibles para matrícula</p>
                    <small class="text-muted">Verifique con su coordinador de carrera</small>
                </div>
                <?php else: ?>
                <form method="POST" action="">
                    <div class="row">
                        <?php foreach ($secciones_disponibles as $seccion): 
                            $cupo_disponible = $seccion['cupo_maximo'] - $seccion['cupo_actual'];
                            $cupo_porcentaje = ($seccion['cupo_actual'] / $seccion['cupo_maximo']) * 100;
                        ?>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border <?php echo ($cupo_disponible <= 3) ? 'border-warning' : ''; ?>">
                                <div class="card-body">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" 
                                               name="secciones[]" 
                                               value="<?php echo $seccion['id']; ?>" 
                                               id="seccion_<?php echo $seccion['id']; ?>">
                                        <label class="form-check-label fw-bold" for="seccion_<?php echo $seccion['id']; ?>">
                                            <?php echo $seccion['curso_nombre']; ?>
                                        </label>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <small class="text-muted d-block">
                                            <i class="fas fa-hashtag me-1"></i>
                                            <?php echo $seccion['curso_codigo']; ?> - Sección <?php echo $seccion['codigo_seccion']; ?>
                                        </small>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-user-tie me-1"></i>
                                            <?php echo $seccion['docente_nombre'] . ' ' . $seccion['docente_apellido']; ?>
                                        </small>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-clock me-1"></i><?php echo $seccion['horario']; ?>
                                        </small>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-door-open me-1"></i><?php echo $seccion['aula']; ?>
                                        </small>
                                        <?php if ($seccion['prerequisito_id']): ?>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            Prerrequisito: <?php echo $seccion['prerequisito_nombre']; ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div>
                                            <span class="badge bg-info"><?php echo $seccion['creditos']; ?> créditos</span>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted d-block">
                                                Cupo: <?php echo $cupo_disponible; ?> / <?php echo $seccion['cupo_maximo']; ?>
                                            </small>
                                            <div class="progress" style="height: 5px; width: 100px;">
                                                <div class="progress-bar <?php echo ($cupo_porcentaje > 80) ? 'bg-warning' : 'bg-success'; ?>" 
                                                     role="progressbar" 
                                                     style="width: <?php echo $cupo_porcentaje; ?>%">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="confirmar_matricula" required>
                                    <label class="form-check-label" for="confirmar_matricula">
                                        Confirmo que he revisado los horarios y no tengo conflictos de tiempo entre las secciones seleccionadas.
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <button type="submit" name="matricular" class="btn btn-unexca btn-lg">
                                    <i class="fas fa-check-circle me-2"></i>Confirmar Matrícula
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de horario -->
<div class="modal fade" id="modalHorario" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mi Horario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Lunes</th>
                                <th>Martes</th>
                                <th>Miércoles</th>
                                <th>Jueves</th>
                                <th>Viernes</th>
                                <th>Sábado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Generar horario de 7am a 9pm
                            for ($hora = 7; $hora <= 21; $hora++): 
                                $hora_display = str_pad($hora, 2, '0', STR_PAD_LEFT) . ':00';
                            ?>
                            <tr>
                                <td><strong><?php echo $hora_display; ?></strong></td>
                                <?php for ($dia = 1; $dia <= 6; $dia++): ?>
                                <td>
                                    <?php
                                    $encontrado = false;
                                    foreach ($cursos_matriculados as $curso) {
                                        // Simulación simple de horarios
                                        if (strpos($curso['horario'], 'Lunes') !== false && $dia == 1) {
                                            echo '<span class="badge bg-primary">' . $curso['curso_codigo'] . '</span>';
                                            $encontrado = true;
                                            break;
                                        }
                                    }
                                    if (!$encontrado) echo '&nbsp;';
                                    ?>
                                </td>
                                <?php endfor; ?>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validar que no haya conflictos de horario
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('input[name="secciones[]"]');
    const horarios = {};
    
    // Extraer horarios de las secciones
    checkboxes.forEach(checkbox => {
        const card = checkbox.closest('.card');
        const horarioText = card.querySelector('.fa-clock').parentElement.textContent.trim();
        horarios[checkbox.value] = parseHorario(horarioText);
    });
    
    // Validar al cambiar checkboxes
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            validarConflictosHorario();
        });
    });
    
    function parseHorario(horarioText) {
        // Implementar lógica para parsear horarios
        // Esto es un ejemplo simplificado
        return horarioText;
    }
    
    function validarConflictosHorario() {
        // Implementar validación de conflictos
        // Retorna true si hay conflictos
        return false;
    }
});

// Mostrar modal de horario
function mostrarHorario() {
    const modal = new bootstrap.Modal(document.getElementById('modalHorario'));
    modal.show();
}
</script>

<?php include '../../includes/footer.php'; ?>