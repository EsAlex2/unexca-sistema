<?php
// includes/header.php
if (!isset($_SESSION['logged_in'])) {
    header('Location: ../index.php');
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME . ' - ' . $page_title ?? 'Dashboard'; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <!-- Estilos UNEXCA -->
    <link rel="stylesheet" href="../assets/css/unexca.css">
    
    <style>
        .content-wrapper {
            min-height: calc(100vh - 56px);
            background-color: #f5f7fa;
        }
        
        .main-content {
            padding: 20px;
        }
        
        .page-header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .breadcrumb {
            background: transparent;
            margin: 0;
            padding: 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-unexca navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-university me-2"></i>
                <span class="fw-bold">UNEXCA</span>
                <small class="d-none d-md-inline"> - Sistema Académico</small>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo $_SESSION['username']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Perfil</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Configuración</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse" id="sidebar">
                <div class="sidebar-sticky pt-3">
                    <?php
                    $menu_items = [];
                    
                    switch ($_SESSION['rol']) {
                        case 'estudiante':
                            $menu_items = [
                                'dashboard.php' => ['icon' => 'fas fa-home', 'text' => 'Dashboard'],
                                'perfil.php' => ['icon' => 'fas fa-user', 'text' => 'Mi Perfil'],
                                'notas.php' => ['icon' => 'fas fa-graduation-cap', 'text' => 'Mis Notas'],
                                'horario.php' => ['icon' => 'fas fa-calendar-alt', 'text' => 'Horario'],
                                'matricula.php' => ['icon' => 'fas fa-book', 'text' => 'Matrícula'],
                                'pagos.php' => ['icon' => 'fas fa-credit-card', 'text' => 'Pagos'],
                                'mensajes.php' => ['icon' => 'fas fa-envelope', 'text' => 'Mensajes']
                            ];
                            break;
                            
                        case 'docente':
                            $menu_items = [
                                'dashboard.php' => ['icon' => 'fas fa-home', 'text' => 'Dashboard'],
                                'cursos.php' => ['icon' => 'fas fa-book-open', 'text' => 'Mis Cursos'],
                                'calificaciones.php' => ['icon' => 'fas fa-edit', 'text' => 'Calificaciones'],
                                'asistencia.php' => ['icon' => 'fas fa-clipboard-list', 'text' => 'Asistencia'],
                                'horario.php' => ['icon' => 'fas fa-calendar-alt', 'text' => 'Horario'],
                                'mensajes.php' => ['icon' => 'fas fa-envelope', 'text' => 'Mensajes']
                            ];
                            break;
                            
                        case 'administrador':
                            $menu_items = [
                                'dashboard.php' => ['icon' => 'fas fa-home', 'text' => 'Dashboard'],
                                'estudiantes.php' => ['icon' => 'fas fa-users', 'text' => 'Estudiantes'],
                                'docentes.php' => ['icon' => 'fas fa-chalkboard-teacher', 'text' => 'Docentes'],
                                'cursos.php' => ['icon' => 'fas fa-book', 'text' => 'Cursos'],
                                'secciones.php' => ['icon' => 'fas fa-layer-group', 'text' => 'Secciones'],
                                'calificaciones.php' => ['icon' => 'fas fa-chart-bar', 'text' => 'Calificaciones'],
                                'pagos.php' => ['icon' => 'fas fa-money-check-alt', 'text' => 'Pagos'],
                                'reportes.php' => ['icon' => 'fas fa-chart-pie', 'text' => 'Reportes'],
                                'configuracion.php' => ['icon' => 'fas fa-cog', 'text' => 'Configuración']
                            ];
                            break;
                    }
                    ?>
                    
                    <ul class="nav flex-column">
                        <?php foreach ($menu_items as $page => $item): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == $page) ? 'active' : ''; ?>" 
                               href="<?php echo $page; ?>">
                                <i class="<?php echo $item['icon']; ?>"></i>
                                <?php echo $item['text']; ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <div class="mt-4 p-3 bg-light rounded">
                        <h6 class="text-center mb-3">Información Rápida</h6>
                        <?php if ($_SESSION['rol'] == 'estudiante'): ?>
                        <div class="small">
                            <p><i class="fas fa-info-circle text-primary me-1"></i> 
                               Semestre: 5to</p>
                            <p><i class="fas fa-star text-warning me-1"></i> 
                               Promedio: 18.5</p>
                            <p><i class="fas fa-check-circle text-success me-1"></i> 
                               Créditos: 120/180</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ms-sm-auto px-0">
                <div class="content-wrapper">
                    <main class="main-content">
                        <!-- Breadcrumb -->
                        <div class="page-header">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h1 class="h3 mb-0"><?php echo $page_title ?? 'Dashboard'; ?></h1>
                                    <nav aria-label="breadcrumb">
                                        <ol class="breadcrumb mb-0">
                                            <li class="breadcrumb-item"><a href="#">Inicio</a></li>
                                            <li class="breadcrumb-item active"><?php echo $page_title ?? 'Dashboard'; ?></li>
                                        </ol>
                                    </nav>
                                </div>
                                <div class="col-auto">
                                    <button class="btn btn-unexca d-md-none" type="button" data-bs-toggle="collapse" 
                                            data-bs-target="#sidebar" aria-controls="sidebar">
                                        <i class="fas fa-bars"></i>
                                    </button>
                                </div>
                            </div>
                        </div>