<?php
// modules/administrativo/ajax/obtener_secuencia_seccion.php
require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SESSION['rol'] != 'administrador') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

header('Content-Type: application/json');

$db = new Database();
$conn = $db->getConnection();

$curso_id = $_GET['curso_id'] ?? 0;
$periodo = $_GET['periodo'] ?? '';

if (!$curso_id || !$periodo) {
    echo json_encode(['secuencia' => 1]);
    exit();
}

// Obtener la última secuencia para este curso en este período
$query = "SELECT MAX(CAST(SUBSTRING_INDEX(codigo_seccion, '-', -1) AS UNSIGNED)) as ultima_secuencia 
          FROM secciones 
          WHERE curso_id = :curso_id 
          AND periodo_academico = :periodo 
          AND codigo_seccion REGEXP '^[A-Z]+-[0-9]+$'";
$stmt = $conn->prepare($query);
$stmt->execute([':curso_id' => $curso_id, ':periodo' => $periodo]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

$secuencia = ($result['ultima_secuencia'] ?? 0) + 1;
echo json_encode(['secuencia' => $secuencia]);
?>