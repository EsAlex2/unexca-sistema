<?php
// modules/administrativo/ajax/obtener_backups.php
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

try {
    // Obtener backups de la base de datos
    $query = "SELECT * FROM backups ORDER BY fecha_creacion DESC LIMIT 10";
    $stmt = $conn->query($query);
    $backups_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar archivos físicos
    $backup_dir = '../../backups/';
    $backups = [];
    
    if (file_exists($backup_dir)) {
        $files = scandir($backup_dir);
        
        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
                $filepath = $backup_dir . $file;
                $fileinfo = [
                    'archivo' => $file,
                    'fecha' => date('Y-m-d H:i:s', filemtime($filepath)),
                    'tamano' => filesize($filepath) > 1024 * 1024 
                        ? round(filesize($filepath) / 1024 / 1024, 2) . ' MB'
                        : round(filesize($filepath) / 1024, 2) . ' KB',
                    'tipo' => 'archivo',
                    'estado' => 'completo'
                ];
                
                // Buscar información adicional en la base de datos
                foreach ($backups_db as $backup_db) {
                    if ($backup_db['nombre_archivo'] == $file) {
                        $fileinfo['tipo'] = $backup_db['tipo'];
                        $fileinfo['estado'] = $backup_db['estado'];
                        break;
                    }
                }
                
                $backups[] = $fileinfo;
            }
        }
    }
    
    // Ordenar por fecha (más reciente primero)
    usort($backups, function($a, $b) {
        return strtotime($b['fecha']) - strtotime($a['fecha']);
    });
    
    echo json_encode(array_slice($backups, 0, 10));
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>