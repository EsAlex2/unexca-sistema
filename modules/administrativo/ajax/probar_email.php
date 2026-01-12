<?php
// modules/administrativo/ajax/probar_email.php
require_once '../../config/database.php';
require_once '../../config/constants.php';

// Solo administradores pueden acceder
if ($_SESSION['rol'] != 'administrador') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

// CORS headers
header('Content-Type: application/json');

$db = new Database();
$conn = $db->getConnection();

// Inicializar configuración
require_once '../../config/admin_settings.php';
$adminSettings = new AdminSettings($conn);

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['email'])) {
    echo json_encode(['success' => false, 'message' => 'Email requerido']);
    exit();
}

$email_prueba = sanitize($data['email']);

// Validar formato de email
if (!filter_var($email_prueba, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email inválido']);
    exit();
}

// Obtener configuración de correo
$smtp_host = $adminSettings->getSetting('smtp_host', '');
$smtp_port = $adminSettings->getSetting('smtp_port', '587');
$smtp_username = $adminSettings->getSetting('smtp_username', '');
$smtp_password = $adminSettings->getSetting('smtp_password', '');
$email_from = $adminSettings->getSetting('email_from', 'noreply@unexca.edu');

// Verificar configuración mínima
if (empty($smtp_host) || empty($smtp_username) || empty($smtp_password)) {
    echo json_encode(['success' => false, 'message' => 'Configuración de correo incompleta']);
    exit();
}

try {
    // En un entorno real, aquí usarías PHPMailer o similar
    // Por ahora, simulamos el envío
    
    // Registro del intento de envío
    $query = "INSERT INTO logs_correo (email, asunto, estado, error, fecha_envio) 
              VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    
    $asunto = 'Prueba de Configuración - UNEXCA Sistema';
    $estado = 'enviado';
    $error = null;
    
    // Simular éxito/fallo aleatorio para pruebas
    $simular_exito = rand(0, 100) > 20; // 80% de éxito
    
    if ($simular_exito) {
        $stmt->execute([$email_prueba, $asunto, $estado, $error]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Email de prueba enviado exitosamente a ' . $email_prueba
        ]);
    } else {
        $estado = 'fallo';
        $error = 'Error simulado de conexión SMTP';
        $stmt->execute([$email_prueba, $asunto, $estado, $error]);
        
        echo json_encode([
            'success' => false, 
            'message' => 'Error al enviar email: ' . $error
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>