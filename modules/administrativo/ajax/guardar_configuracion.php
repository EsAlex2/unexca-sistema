<?php
// modules/administrativo/ajax/guardar_configuracion.php
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
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$db = new Database();
$conn = $db->getConnection();

// Inicializar configuración
require_once '../../config/admin_settings.php';
$adminSettings = new AdminSettings($conn);

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

$clave = isset($data['clave']) ? sanitize($data['clave']) : null;
$valor = isset($data['valor']) ? sanitize($data['valor']) : null;

if (!$clave || !isset($valor)) {
    echo json_encode(['success' => false, 'message' => 'Clave y valor son requeridos']);
    exit();
}

// Validar valores según el tipo de configuración
$config_validations = [
    'nota_minima' => function($val) {
        $val = floatval($val);
        return $val >= 0 && $val <= 20;
    },
    'nota_excelencia' => function($val) {
        $val = floatval($val);
        return $val >= 0 && $val <= 20;
    },
    'maximo_creditos' => function($val) {
        $val = intval($val);
        return $val > 0 && $val <= 50;
    },
    'monto_matricula' => function($val) {
        $val = floatval($val);
        return $val >= 0;
    },
    'dias_vencimiento' => function($val) {
        $val = intval($val);
        return $val >= 1 && $val <= 365;
    },
    'porcentaje_mora' => function($val) {
        $val = floatval($val);
        return $val >= 0 && $val <= 100;
    },
    'max_intentos_login' => function($val) {
        $val = intval($val);
        return $val >= 1 && $val <= 10;
    },
    'tiempo_bloqueo' => function($val) {
        $val = intval($val);
        return $val >= 1 && $val <= 1440;
    }
];

// Aplicar validación si existe para esta clave
if (isset($config_validations[$clave])) {
    if (!$config_validations[$clave]($valor)) {
        echo json_encode(['success' => false, 'message' => 'Valor inválido para ' . $clave]);
        exit();
    }
}

// Para contraseñas, encriptar si no está vacía
if (strpos($clave, 'password') !== false && !empty($valor)) {
    $valor = password_hash($valor, PASSWORD_DEFAULT);
}

// Guardar configuración
if ($adminSettings->setSetting($clave, $valor)) {
    echo json_encode(['success' => true, 'message' => 'Configuración guardada']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al guardar configuración']);
}
?>