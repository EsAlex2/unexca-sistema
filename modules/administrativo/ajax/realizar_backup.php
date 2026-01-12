<?php
// modules/administrativo/ajax/realizar_backup.php
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

// Directorio de backups
$backup_dir = '../../backups/';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Nombre del archivo de backup
$timestamp = date('Y-m-d_H-i-s');
$filename = "backup_{$timestamp}.sql";
$filepath = $backup_dir . $filename;

try {
    // Obtener todas las tablas
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    if (empty($tables)) {
        echo json_encode(['success' => false, 'message' => 'No hay tablas para respaldar']);
        exit();
    }
    
    // Crear archivo de backup
    $output = "";
    $output .= "-- UNEXCA Database Backup\n";
    $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $output .= "-- Host: localhost\n";
    $output .= "-- Database: unexca\n\n";
    
    // Recorrer cada tabla
    foreach ($tables as $table) {
        // Obtener estructura de la tabla
        $output .= "--\n";
        $output .= "-- Table structure for table `{$table}`\n";
        $output .= "--\n\n";
        $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
        
        $create_table = $conn->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
        $output .= $create_table[1] . ";\n\n";
        
        // Obtener datos de la tabla
        $output .= "--\n";
        $output .= "-- Dumping data for table `{$table}`\n";
        $output .= "--\n\n";
        
        $rows = $conn->query("SELECT * FROM `{$table}`");
        $rowCount = $rows->rowCount();
        
        if ($rowCount > 0) {
            $output .= "INSERT INTO `{$table}` VALUES\n";
            
            $rowNum = 0;
            while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                $rowNum++;
                
                // Escapar valores
                $values = array_map(function($value) use ($conn) {
                    if ($value === null) return 'NULL';
                    return $conn->quote($value);
                }, array_values($row));
                
                $output .= "(" . implode(', ', $values) . ")";
                
                if ($rowNum < $rowCount) {
                    $output .= ",\n";
                } else {
                    $output .= ";\n\n";
                }
            }
        } else {
            $output .= "-- No data for table `{$table}`\n\n";
        }
    }
    
    // Escribir archivo
    if (file_put_contents($filepath, $output)) {
        $filesize = filesize($filepath);
        $size_formatted = $filesize > 1024 * 1024 
            ? round($filesize / 1024 / 1024, 2) . ' MB'
            : round($filesize / 1024, 2) . ' KB';
        
        // Registrar en la base de datos
        $query = "INSERT INTO backups (nombre_archivo, tipo, tamano, estado, fecha_creacion) 
                  VALUES (?, 'manual', ?, 'completo', NOW())";
        $stmt = $conn->prepare($query);
        $stmt->execute([$filename, $size_formatted]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Backup creado exitosamente',
            'filename' => $filename,
            'size' => $size_formatted
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al escribir archivo de backup']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>