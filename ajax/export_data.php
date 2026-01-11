<?php
// ajax/export_data.php
require_once '../config/database.php';
require_once '../config/constants.php';

if ($_SESSION['rol'] != 'administrador') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

$format = $_GET['format'] ?? 'excel';
$table = $_GET['table'] ?? '';
$filters = json_decode($_GET['filters'] ?? '[]', true);

$db = new Database();
$conn = $db->getConnection();

// Generar datos según el formato
switch ($format) {
    case 'excel':
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="reporte_' . date('Y-m-d') . '.xlsx"');
        
        // En producción, usar una biblioteca como PhpSpreadsheet
        echo "ID,Nombre,Email,Rol,Estado\n";
        echo "1,Admin,admin@unexca.edu.ve,Administrador,Activo\n";
        // ... más datos
        break;
        
    case 'pdf':
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="reporte_' . date('Y-m-d') . '.pdf"');
        
        // En producción, usar una biblioteca como TCPDF o DomPDF
        echo "%PDF-1.4\n";
        // ... contenido PDF
        break;
        
    case 'csv':
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="reporte_' . date('Y-m-d') . '.csv"');
        
        $data = generateCSVData($conn, $table, $filters);
        echo $data;
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Formato no soportado']);
}

function generateCSVData($conn, $table, $filters) {
    $csv = [];
    
    switch ($table) {
        case 'estudiantes':
            $query = "SELECT codigo_estudiante, cedula, nombres, apellidos, email, 
                             telefono, fecha_ingreso, estado 
                      FROM estudiantes";
            // Aplicar filtros...
            break;
            
        case 'docentes':
            $query = "SELECT codigo_docente, cedula, nombres, apellidos, email, 
                             telefono, titulo_academico, especialidad, estado 
                      FROM docentes";
            break;
            
        case 'pagos':
            $query = "SELECT p.*, e.codigo_estudiante, e.nombres, e.apellidos 
                      FROM pagos p 
                      JOIN estudiantes e ON p.estudiante_id = e.id";
            break;
            
        default:
            return '';
    }
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($data)) {
        return '';
    }
    
    // Encabezados
    $csv[] = implode(',', array_keys($data[0]));
    
    // Datos
    foreach ($data as $row) {
        $csv[] = implode(',', array_map(function($value) {
            return '"' . str_replace('"', '""', $value) . '"';
        }, $row));
    }
    
    return implode("\n", $csv);
}
?>