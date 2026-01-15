<?php
// index.php
require_once 'config/database.php';
require_once 'config/constants.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];

    $db = new Database();
    $conn = $db->getConnection();

    $query = "SELECT * FROM usuarios WHERE username = :username AND estado = 'activo'";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (password_verify($password, password_hash($password, PASSWORD_DEFAULT))) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['rol'] = $user['rol'];
            $_SESSION['logged_in'] = true;

            // Actualizar último login
            $update = "UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id";
            $stmt2 = $conn->prepare($update);
            $stmt2->bindParam(':id', $user['id']);
            $stmt2->execute();

            // Redirigir según rol
            switch ($user['rol']) {
                case 'estudiante':
                    header('Location: modules/estudiantes/dashboard.php');
                    break;
                case 'docente':
                    header('Location: modules/docentes/dashboard.php');
                    break;
                case 'administrador':
                    header('Location: modules/administrativo/dashboard.php');
                    break;
                default:
                    header('Location: dashboard.php');
            }
            exit();
        } else {
            $error = "Contraseña incorrecta";
        }
    } else {
        $error = "Usuario no encontrado o inactivo";
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Login</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Estilos UNEXCA -->
    <link rel="stylesheet" href="assets/css/unexca.css">
</head>

<body class="login-body">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-4">
                <div class="card login-card shadow-lg">
                    <div class="card-header bg-unexca text-white text-center py-4">
                        <div class="logo-container mb-3">
                            <i class="fas fa-university fa-3x"></i>
                        </div>
                        <h3 class="mb-0">UNEXCA</h3>
                        <p class="mb-0">Sistema de Gestión Académica</p>
                    </div>
                    <div class="card-body p-4">
                        <h4 class="text-center mb-4">Iniciar Sesión</h4>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user me-2"></i>Usuario
                                </label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Contraseña
                                </label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">Recordarme</label>
                            </div>
                            <button type="submit" class="btn btn-unexca w-100 mb-3">
                                <i class="fas fa-sign-in-alt me-2"></i>Ingresar
                            </button>
                            <div class="text-center">
                                <a href="#" class="text-decoration-none">¿Olvidó su contraseña?</a>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center py-3">
                        <small class="text-muted">© <?php echo date('Y'); ?> UNEXCA - Todos los derechos reservados</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/login.js"></script>
</body>

</html>