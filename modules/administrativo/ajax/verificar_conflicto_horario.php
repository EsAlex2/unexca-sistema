<?php
// modules/administrativo/ajax/verificar_conflicto_horario.php
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

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['conflicto' => false]);
    exit();
}

$docente_id = $data['docente_id'] ?? 0;
$periodo = $data['periodo'] ?? '';
$horario = $data['horario'] ?? '';
$seccion_id = $data['seccion_id'] ?? 0;

if (!$docente_id || !$periodo || !$horario) {
    echo json_encode(['conflicto' => false]);
    exit();
}

// Extraer días y horas del horario
preg_match_all('/(Lunes|Martes|Miércoles|Jueves|Viernes|Sábado|Domingo)\s+(\d{2}:\d{2})-(\d{2}:\d{2})/i', $horario, $matches, PREG_SET_ORDER);

$conflictos = [];

foreach ($matches as $match) {
    $dia = $match[1];
    $hora_inicio = $match[2];
    $hora_fin = $match[3];
    
    // Verificar conflictos
    $query = "SELECT s.codigo_seccion, c.nombre as curso_nombre, s.horario
              FROM secciones s 
              JOIN cursos c ON s.curso_id = c.id 
              WHERE s.docente_id = :docente_id 
              AND s.periodo_academico = :periodo 
              AND s.estado IN ('abierta', 'en_progreso')
              AND s.id != :seccion_id
              AND (s.horario LIKE :dia_inicio OR s.horario LIKE :dia_medio)";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':docente_id' => $docente_id,
        ':periodo' => $periodo,
        ':seccion_id' => $seccion_id,
        ':dia_inicio' => "%$dia $hora_inicio%",
        ':dia_medio' => "%$dia%"
    ]);
    
    $secciones_conflicto = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($secciones_conflicto as $seccion_conflicto) {
        // Verificar superposición de horarios más específica
        if (horariosSuperpuestos($horario, $seccion_conflicto['horario'])) {
            $conflictos[] = $seccion_conflicto['curso_nombre'] . ' (' . $seccion_conflicto['codigo_seccion'] . ')';
        }
    }
}

if (!empty($conflictos)) {
    echo json_encode([
        'conflicto' => true,
        'mensaje' => 'Conflicto con: ' . implode(', ', array_unique($conflictos))
    ]);
} else {
    echo json_encode(['conflicto' => false]);
}

function horariosSuperpuestos($horario1, $horario2) {
    // Esta función implementaría la lógica de superposición de horarios
    // Por simplicidad, retornamos true si comparten algún día
    $dias1 = extraerDias($horario1);
    $dias2 = extraerDias($horario2);
    
    return !empty(array_intersect($dias1, $dias2));
}

function extraerDias($horario) {
    preg_match_all('/(Lunes|Martes|Miércoles|Jueves|Viernes|Sábado|Domingo)/i', $horario, $matches);
    return array_unique(array_map('strtolower', $matches[0]));
}
?>