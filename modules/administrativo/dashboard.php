<?php
// modules/administrativo/dashboard.php
require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SESSION['rol'] != 'administrador') {
    header('Location: ../../index.php');
    exit();
}

$page_title = 'Dashboard Administrativo';

// Obtener estadísticas
$db = new Database();
$conn = $db->getConnection();

// Total estudiantes
$query = "SELECT COUNT(*) as total FROM estudiantes";
$stmt = $conn->query($query);
$total_estudiantes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total docentes
$query = "SELECT COUNT(*) as total FROM docentes WHERE estado = 'activo'";
$stmt = $conn->query($query);
$total_docentes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total cursos
$query = "SELECT COUNT(*) as total FROM cursos WHERE estado = 'activo'";
$stmt = $conn->query($query);
$total_cursos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Matrículas pendientes
$query = "SELECT COUNT(*) as total FROM pagos WHERE estado = 'pendiente' AND concepto LIKE '%matrícula%'";
$stmt = $conn->query($query);
$matriculas_pendientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Últimos estudiantes registrados
$query = "SELECT e.*, c.nombre as carrera 
          FROM estudiantes e 
          LEFT JOIN carreras c ON e.carrera_id = c.id 
          ORDER BY e.id DESC LIMIT 5";
$stmt = $conn->query($query);
$ultimos_estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cursos más populares
$query = "SELECT c.nombre, COUNT(m.id) as inscritos 
          FROM cursos c 
          LEFT JOIN secciones s ON c.id = s.curso_id 
          LEFT JOIN matriculas m ON s.id = m.seccion_id 
          GROUP BY c.id 
          ORDER BY inscritos DESC 
          LIMIT 5";
$stmt = $conn->query($query);
$cursos_populares = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="row">
    <!-- Widgets de estadísticas -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="widget widget-1">
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
        <div class="widget widget-2">
            <div class="row no-gutters align-items-center">
                <div class="col me-2">
                    <div class="text-uppercase mb-1">Docentes</div>
                    <div class="h5 mb-0"><?php echo $total_docentes; ?></div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-chalkboard-teacher fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="widget widget-3">
            <div class="row no-gutters align-items-center">
                <div class="col me-2">
                    <div class="text-uppercase mb-1">Cursos Activos</div>
                    <div class="h5 mb-0"><?php echo $total_cursos; ?></div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-book fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="widget widget-4">
            <div class="row no-gutters align-items-center">
                <div class="col me-2">
                    <div class="text-uppercase mb-1">Matrículas Pend.</div>
                    <div class="h5 mb-0"><?php echo $matriculas_pendientes; ?></div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-clock fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Últimos estudiantes -->
    <div class="col-xl-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Últimos Estudiantes Registrados</h6>
                <a href="estudiantes.php?action=nuevo" class="btn btn-sm btn-unexca">Nuevo</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Carrera</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimos_estudiantes as $estudiante): ?>
                            <tr>
                                <td><?php echo $estudiante['codigo_estudiante']; ?></td>
                                <td><?php echo $estudiante['nombres'] . ' ' . $estudiante['apellidos']; ?></td>
                                <td><?php echo $estudiante['carrera'] ?? 'No asignada'; ?></td>
                                <td>
                                    <a href="estudiantes.php?action=ver&id=<?php echo $estudiante['id']; ?>" 
                                       class="btn btn-sm btn-outline-unexca">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cursos más populares -->
    <div class="col-xl-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Cursos Más Populares</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Curso</th>
                                <th>Inscritos</th>
                                <th>Progreso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cursos_populares as $curso): 
                                $porcentaje = min(100, ($curso['inscritos'] / 50) * 100);
                            ?>
                            <tr>
                                <td><?php echo $curso['nombre']; ?></td>
                                <td><?php echo $curso['inscritos']; ?></td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?php echo $porcentaje; ?>%">
                                            <?php echo round($porcentaje); ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Gráfico de estadísticas -->
    <div class="col-xl-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Matrículas por Semestre</h6>
            </div>
            <div class="card-body">
                <canvas id="matriculasChart" height="200"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Notificaciones recientes -->
    <div class="col-xl-4 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Notificaciones</h6>
                <span class="badge bg-unexca">5 nuevas</span>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <a href="#" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <small class="text-primary">Sistema</small>
                            <small>Hace 5 min</small>
                        </div>
                        <p class="mb-1">Nuevo estudiante registrado</p>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <small class="text-success">Pagos</small>
                            <small>Hace 1 hora</small>
                        </div>
                        <p class="mb-1">Pago de matrícula confirmado</p>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <small class="text-warning">Calificaciones</small>
                            <small>Hace 3 horas</small>
                        </div>
                        <p class="mb-1">Período de carga de notas abierto</p>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Gráfico de matriculas
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('matriculasChart').getContext('2d');
    const matriculasChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
            datasets: [{
                label: 'Matrículas 2024',
                data: [65, 59, 80, 81, 56, 55, 40, 72, 85, 30, 45, 60],
                borderColor: '#0056b3',
                backgroundColor: 'rgba(0, 86, 179, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }, {
                label: 'Matrículas 2023',
                data: [28, 48, 40, 19, 86, 27, 90, 45, 60, 80, 35, 50],
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Evolución de Matrículas'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Cantidad de Matrículas'
                    }
                }
            }
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>