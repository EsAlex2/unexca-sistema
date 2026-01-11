<?php
// modules/calificaciones/libro_calificaciones.php
require_once '../../config/database.php';
require_once '../../config/constants.php';

if (!in_array($_SESSION['rol'], ['docente', 'administrador'])) {
    header('Location: ../../index.php');
    exit();
}

$page_title = 'Libro de Calificaciones';

$db = new Database();
$conn = $db->getConnection();

// Obtener secciones del docente
if ($_SESSION['rol'] == 'docente') {
    $query = "SELECT s.*, c.nombre as curso_nombre, c.codigo as curso_codigo, 
                     COUNT(m.id) as total_estudiantes
              FROM secciones s 
              JOIN cursos c ON s.curso_id = c.id 
              LEFT JOIN matriculas m ON s.id = m.seccion_id AND m.estado = 'matriculado'
              WHERE s.docente_id = (SELECT id FROM docentes WHERE usuario_id = :user_id)
              AND s.estado IN ('en_progreso', 'abierta')
              GROUP BY s.id 
              ORDER BY s.periodo_academico DESC, c.nombre";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $secciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Administrador ve todas las secciones
    $query = "SELECT s.*, c.nombre as curso_nombre, c.codigo as curso_codigo, 
                     d.nombres as docente_nombre, d.apellidos as docente_apellido,
                     COUNT(m.id) as total_estudiantes
              FROM secciones s 
              JOIN cursos c ON s.curso_id = c.id 
              JOIN docentes d ON s.docente_id = d.id 
              LEFT JOIN matriculas m ON s.id = m.seccion_id AND m.estado = 'matriculado'
              WHERE s.estado IN ('en_progreso', 'abierta')
              GROUP BY s.id 
              ORDER BY s.periodo_academico DESC, c.nombre";
    $stmt = $conn->query($query);
    $secciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$seccion_id = $_GET['seccion_id'] ?? 0;

// Si se seleccionó una sección
if ($seccion_id > 0) {
    // Validar acceso
    if ($_SESSION['rol'] == 'docente') {
        $query = "SELECT id FROM secciones WHERE docente_id = (SELECT id FROM docentes WHERE usuario_id = :user_id) AND id = :seccion_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':seccion_id', $seccion_id);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            $_SESSION['message'] = 'No tiene permisos para acceder a esta sección';
            $_SESSION['message_type'] = 'danger';
            header('Location: libro_calificaciones.php');
            exit();
        }
    }
    
    // Obtener información de la sección
    $query = "SELECT s.*, c.nombre as curso_nombre, c.codigo as curso_codigo,
                     c.creditos, d.nombres as docente_nombre, d.apellidos as docente_apellido
              FROM secciones s 
              JOIN cursos c ON s.curso_id = c.id 
              JOIN docentes d ON s.docente_id = d.id 
              WHERE s.id = :seccion_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':seccion_id', $seccion_id);
    $stmt->execute();
    $seccion_actual = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener tipos de evaluación
    $query = "SELECT * FROM tipos_evaluacion WHERE seccion_id = :seccion_id ORDER BY orden";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':seccion_id', $seccion_id);
    $stmt->execute();
    $tipos_evaluacion = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estudiantes matriculados
    $query = "SELECT e.*, m.id as matricula_id, u.email
              FROM estudiantes e 
              JOIN matriculas m ON e.id = m.estudiante_id 
              JOIN usuarios u ON e.usuario_id = u.id 
              WHERE m.seccion_id = :seccion_id AND m.estado = 'matriculado'
              ORDER BY e.apellidos, e.nombres";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':seccion_id', $seccion_id);
    $stmt->execute();
    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener calificaciones existentes
    $calificaciones = [];
    if (!empty($estudiantes) && !empty($tipos_evaluacion)) {
        $query = "SELECT c.matricula_id, c.tipo_evaluacion_id, c.nota 
                  FROM calificaciones c 
                  JOIN matriculas m ON c.matricula_id = m.id 
                  WHERE m.seccion_id = :seccion_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':seccion_id', $seccion_id);
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $calificaciones[$row['matricula_id']][$row['tipo_evaluacion_id']] = $row['nota'];
        }
    }
    
    // Procesar guardado de calificaciones
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_calificaciones'])) {
        $conn->beginTransaction();
        
        try {
            foreach ($estudiantes as $estudiante) {
                foreach ($tipos_evaluacion as $tipo) {
                    $nota = $_POST['nota_' . $estudiante['matricula_id'] . '_' . $tipo['id']] ?? null;
                    
                    if ($nota !== null && $nota !== '') {
                        $nota = floatval($nota);
                        
                        // Verificar si ya existe
                        $query = "SELECT id FROM calificaciones 
                                  WHERE matricula_id = :matricula_id 
                                  AND tipo_evaluacion_id = :tipo_evaluacion_id";
                        $stmt = $conn->prepare($query);
                        $stmt->bindParam(':matricula_id', $estudiante['matricula_id']);
                        $stmt->bindParam(':tipo_evaluacion_id', $tipo['id']);
                        $stmt->execute();
                        
                        if ($stmt->rowCount() > 0) {
                            // Actualizar
                            $query = "UPDATE calificaciones SET nota = :nota 
                                      WHERE matricula_id = :matricula_id 
                                      AND tipo_evaluacion_id = :tipo_evaluacion_id";
                        } else {
                            // Insertar
                            $query = "INSERT INTO calificaciones (matricula_id, tipo_evaluacion_id, nota) 
                                      VALUES (:matricula_id, :tipo_evaluacion_id, :nota)";
                        }
                        
                        $stmt = $conn->prepare($query);
                        $stmt->bindParam(':matricula_id', $estudiante['matricula_id']);
                        $stmt->bindParam(':tipo_evaluacion_id', $tipo['id']);
                        $stmt->bindParam(':nota', $nota);
                        $stmt->execute();
                    }
                }
            }
            
            $conn->commit();
            $_SESSION['message'] = 'Calificaciones guardadas correctamente';
            $_SESSION['message_type'] = 'success';
            
            // Recalcular notas finales
            recalcularNotasFinales($conn, $seccion_id);
            
            header('Location: libro_calificaciones.php?seccion_id=' . $seccion_id);
            exit();
            
        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['message'] = 'Error al guardar las calificaciones: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }
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
        <h5 class="mb-0">Libro de Calificaciones</h5>
        <?php if ($seccion_id > 0): ?>
        <a href="libro_calificaciones.php" class="btn btn-outline-unexca">
            <i class="fas fa-arrow-left me-1"></i>Volver
        </a>
        <?php endif; ?>
    </div>
    
    <div class="card-body">
        <?php if ($seccion_id == 0): ?>
        <!-- Listado de secciones -->
        <div class="row">
            <?php if (empty($secciones)): ?>
            <div class="col-md-12 text-center py-5">
                <i class="fas fa-book fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No tienes secciones asignadas</h5>
                <p class="text-muted">Contacta con la administración para asignarte cursos</p>
            </div>
            <?php else: ?>
            <?php foreach ($secciones as $seccion): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h6 class="mb-1"><?php echo $seccion['curso_nombre']; ?></h6>
                                <small class="text-muted"><?php echo $seccion['curso_codigo']; ?></small>
                            </div>
                            <span class="badge bg-unexca"><?php echo $seccion['total_estudiantes']; ?> estudiantes</span>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">
                                <i class="fas fa-user-tie me-1"></i>
                                <?php if ($_SESSION['rol'] == 'administrador'): ?>
                                <?php echo $seccion['docente_nombre'] . ' ' . $seccion['docente_apellido']; ?>
                                <?php else: ?>
                                Tú
                                <?php endif; ?>
                            </small>
                            <small class="text-muted d-block">
                                <i class="fas fa-calendar me-1"></i><?php echo $seccion['periodo_academico']; ?>
                            </small>
                            <small class="text-muted d-block">
                                <i class="fas fa-clock me-1"></i><?php echo $seccion['horario']; ?>
                            </small>
                        </div>
                        
                        <div class="d-grid">
                            <a href="libro_calificaciones.php?seccion_id=<?php echo $seccion['id']; ?>" 
                               class="btn btn-outline-unexca">
                                <i class="fas fa-edit me-1"></i>Gestionar Calificaciones
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <?php else: ?>
        <!-- Libro de calificaciones de una sección específica -->
        <div class="mb-4">
            <div class="alert alert-unexca">
                <div class="row">
                    <div class="col-md-8">
                        <h5 class="mb-1"><?php echo $seccion_actual['curso_nombre']; ?></h5>
                        <p class="mb-1">
                            <strong>Sección:</strong> <?php echo $seccion_actual['codigo_seccion']; ?> | 
                            <strong>Período:</strong> <?php echo $seccion_actual['periodo_academico']; ?> | 
                            <strong>Créditos:</strong> <?php echo $seccion_actual['creditos']; ?>
                        </p>
                        <p class="mb-0">
                            <strong>Docente:</strong> <?php echo $seccion_actual['docente_nombre'] . ' ' . $seccion_actual['docente_apellido']; ?> | 
                            <strong>Horario:</strong> <?php echo $seccion_actual['horario']; ?> | 
                            <strong>Aula:</strong> <?php echo $seccion_actual['aula']; ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="tipos_evaluacion.php?seccion_id=<?php echo $seccion_id; ?>" 
                           class="btn btn-unexca">
                            <i class="fas fa-cog me-1"></i>Configurar Evaluaciones
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (empty($tipos_evaluacion)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            No hay tipos de evaluación configurados. 
            <a href="tipos_evaluacion.php?seccion_id=<?php echo $seccion_id; ?>" class="alert-link">
                Configurar ahora
            </a>
        </div>
        <?php elseif (empty($estudiantes)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            No hay estudiantes matriculados en esta sección.
        </div>
        <?php else: ?>
        <!-- Formulario de calificaciones -->
        <form method="POST" action="">
            <input type="hidden" name="guardar_calificaciones" value="1">
            
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th rowspan="2" style="vertical-align: middle;">#</th>
                            <th rowspan="2" style="vertical-align: middle;">Estudiante</th>
                            <th rowspan="2" style="vertical-align: middle;">Código</th>
                            <?php foreach ($tipos_evaluacion as $tipo): ?>
                            <th class="text-center" title="<?php echo $tipo['descripcion']; ?>">
                                <?php echo $tipo['nombre']; ?>
                                <br>
                                <small class="text-muted">(<?php echo $tipo['peso']; ?>%)</small>
                            </th>
                            <?php endforeach; ?>
                            <th rowspan="2" class="text-center" style="vertical-align: middle;">Nota Final</th>
                            <th rowspan="2" class="text-center" style="vertical-align: middle;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estudiantes as $index => $estudiante): 
                            // Calcular nota final
                            $nota_final = 0;
                            $total_peso = 0;
                            $todas_notas = true;
                            
                            foreach ($tipos_evaluacion as $tipo) {
                                $nota = $calificaciones[$estudiante['matricula_id']][$tipo['id']] ?? null;
                                if ($nota !== null) {
                                    $nota_final += $nota * ($tipo['peso'] / 100);
                                    $total_peso += $tipo['peso'];
                                } else {
                                    $todas_notas = false;
                                }
                            }
                            
                            if ($total_peso > 0 && $todas_notas) {
                                $nota_final = round($nota_final * (100 / $total_peso), 2);
                            } else {
                                $nota_final = null;
                            }
                            
                            // Determinar estado
                            if ($nota_final !== null) {
                                if ($nota_final >= 10) {
                                    $estado = 'Aprobado';
                                    $estado_class = 'success';
                                } else {
                                    $estado = 'Reprobado';
                                    $estado_class = 'danger';
                                }
                            } else {
                                $estado = 'Pendiente';
                                $estado_class = 'warning';
                            }
                        ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <strong><?php echo $estudiante['nombres'] . ' ' . $estudiante['apellidos']; ?></strong>
                            </td>
                            <td><?php echo $estudiante['codigo_estudiante']; ?></td>
                            
                            <?php foreach ($tipos_evaluacion as $tipo): 
                                $nota_actual = $calificaciones[$estudiante['matricula_id']][$tipo['id']] ?? '';
                            ?>
                            <td class="text-center">
                                <input type="number" 
                                       name="nota_<?php echo $estudiante['matricula_id']; ?>_<?php echo $tipo['id']; ?>"
                                       class="form-control form-control-sm text-center" 
                                       value="<?php echo $nota_actual; ?>"
                                       min="0" max="20" step="0.01"
                                       style="width: 80px; margin: 0 auto;">
                            </td>
                            <?php endforeach; ?>
                            
                            <td class="text-center">
                                <?php if ($nota_final !== null): ?>
                                <span class="badge bg-<?php echo $estado_class; ?> fs-6">
                                    <?php echo number_format($nota_final, 2); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            
                            <td class="text-center">
                                <span class="badge bg-<?php echo $estado_class; ?>">
                                    <?php echo $estado; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <!-- Totales -->
                        <tr class="table-light">
                            <td colspan="<?php echo 3 + count($tipos_evaluacion); ?>" class="text-end">
                                <strong>Promedio de la sección:</strong>
                            </td>
                            <td class="text-center">
                                <?php
                                $suma_notas = 0;
                                $contador = 0;
                                foreach ($estudiantes as $estudiante) {
                                    $nota_final = 0;
                                    $total_peso = 0;
                                    $todas_notas = true;
                                    
                                    foreach ($tipos_evaluacion as $tipo) {
                                        $nota = $calificaciones[$estudiante['matricula_id']][$tipo['id']] ?? null;
                                        if ($nota !== null) {
                                            $nota_final += $nota * ($tipo['peso'] / 100);
                                            $total_peso += $tipo['peso'];
                                        } else {
                                            $todas_notas = false;
                                        }
                                    }
                                    
                                    if ($total_peso > 0 && $todas_notas) {
                                        $nota_final = $nota_final * (100 / $total_peso);
                                        $suma_notas += $nota_final;
                                        $contador++;
                                    }
                                }
                                
                                $promedio_seccion = ($contador > 0) ? round($suma_notas / $contador, 2) : 0;
                                ?>
                                <span class="badge bg-info fs-6">
                                    <?php echo number_format($promedio_seccion, 2); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php
                                $aprobados = 0;
                                $reprobados = 0;
                                $total_estudiantes = count($estudiantes);
                                
                                foreach ($estudiantes as $estudiante) {
                                    $nota_final = 0;
                                    $total_peso = 0;
                                    $todas_notas = true;
                                    
                                    foreach ($tipos_evaluacion as $tipo) {
                                        $nota = $calificaciones[$estudiante['matricula_id']][$tipo['id']] ?? null;
                                        if ($nota !== null) {
                                            $nota_final += $nota * ($tipo['peso'] / 100);
                                            $total_peso += $tipo['peso'];
                                        } else {
                                            $todas_notas = false;
                                        }
                                    }
                                    
                                    if ($total_peso > 0 && $todas_notas) {
                                        $nota_final = $nota_final * (100 / $total_peso);
                                        if ($nota_final >= 10) {
                                            $aprobados++;
                                        } else {
                                            $reprobados++;
                                        }
                                    }
                                }
                                ?>
                                <small>
                                    <?php echo $aprobados; ?> ✔ | <?php echo $reprobados; ?> ✘
                                    <br>
                                    <span class="text-muted">(<?php echo $total_estudiantes; ?> total)</span>
                                </small>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-unexca">
                    <i class="fas fa-save me-2"></i>Guardar Todas las Calificaciones
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="calcularAutomaticamente()">
                    <i class="fas fa-calculator me-2"></i>Calcular Automáticamente
                </button>
            </div>
        </form>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function calcularAutomaticamente() {
    Swal.fire({
        title: '¿Calcular notas automáticamente?',
        text: 'Esta acción generará notas aleatorias para estudiantes sin calificaciones.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, calcular',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Generar notas aleatorias
            const inputs = document.querySelectorAll('input[type="number"]');
            inputs.forEach(input => {
                if (!input.value) {
                    // Nota aleatoria entre 8 y 19
                    const notaAleatoria = (Math.random() * 11 + 8).toFixed(2);
                    input.value = notaAleatoria;
                }
            });
            
            Swal.fire(
                '¡Calculado!',
                'Se han generado notas aleatorias para los campos vacíos.',
                'success'
            );
        }
    });
}

// Función para recalcular notas finales automáticamente al cambiar una nota
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('input[type="number"]');
    inputs.forEach(input => {
        input.addEventListener('change', function() {
            // Aquí podrías implementar el cálculo en tiempo real si es necesario
        });
    });
});
</script>

<?php
// Función para recalcular notas finales
function recalcularNotasFinales($conn, $seccion_id) {
    // Obtener tipos de evaluación con sus pesos
    $query = "SELECT * FROM tipos_evaluacion WHERE seccion_id = :seccion_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':seccion_id', $seccion_id);
    $stmt->execute();
    $tipos_evaluacion = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener matriculas de la sección
    $query = "SELECT m.id, m.estudiante_id 
              FROM matriculas m 
              WHERE m.seccion_id = :seccion_id AND m.estado = 'matriculado'";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':seccion_id', $seccion_id);
    $stmt->execute();
    $matriculas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($matriculas as $matricula) {
        $nota_final = 0;
        $total_peso = 0;
        $todas_notas = true;
        
        foreach ($tipos_evaluacion as $tipo) {
            $query = "SELECT nota FROM calificaciones 
                      WHERE matricula_id = :matricula_id 
                      AND tipo_evaluacion_id = :tipo_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':matricula_id', $matricula['id']);
            $stmt->bindParam(':tipo_id', $tipo['id']);
            $stmt->execute();
            
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $nota_final += $row['nota'] * ($tipo['peso'] / 100);
                $total_peso += $tipo['peso'];
            } else {
                $todas_notas = false;
            }
        }
        
        if ($total_peso > 0 && $todas_notas) {
            $nota_final_calculada = round($nota_final * (100 / $total_peso), 2);
            
            // Actualizar nota final en la matrícula
            $query = "UPDATE matriculas SET nota_final = :nota_final WHERE id = :matricula_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':nota_final', $nota_final_calculada);
            $stmt->bindParam(':matricula_id', $matricula['id']);
            $stmt->execute();
            
            // Actualizar estado según nota final
            $estado = ($nota_final_calculada >= 10) ? 'aprobado' : 'reprobado';
            $query = "UPDATE matriculas SET estado = :estado WHERE id = :matricula_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':estado', $estado);
            $stmt->bindParam(':matricula_id', $matricula['id']);
            $stmt->execute();
            
            // Actualizar créditos del estudiante si aprobó
            if ($estado == 'aprobado') {
                // Obtener créditos del curso
                $query = "SELECT c.creditos FROM cursos c 
                          JOIN secciones s ON c.id = s.curso_id 
                          JOIN matriculas m ON s.id = m.seccion_id 
                          WHERE m.id = :matricula_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':matricula_id', $matricula['id']);
                $stmt->execute();
                $curso = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($curso) {
                    $query = "UPDATE estudiantes 
                              SET creditos_aprobados = creditos_aprobados + :creditos 
                              WHERE id = :estudiante_id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':creditos', $curso['creditos']);
                    $stmt->bindParam(':estudiante_id', $matricula['estudiante_id']);
                    $stmt->execute();
                }
            }
        }
    }
    
    // Recalcular promedios de estudiantes
    $query = "UPDATE estudiantes e
              SET promedio_general = (
                  SELECT AVG(m.nota_final) 
                  FROM matriculas m 
                  WHERE m.estudiante_id = e.id 
                  AND m.nota_final IS NOT NULL
              )
              WHERE id IN (SELECT estudiante_id FROM matriculas WHERE seccion_id = :seccion_id)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':seccion_id', $seccion_id);
    $stmt->execute();
}

include '../../includes/footer.php'; ?>