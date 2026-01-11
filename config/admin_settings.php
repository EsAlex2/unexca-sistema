<?php
// config/admin_settings.php

class AdminSettings {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // Obtener configuración del sistema
    public function getSetting($key, $default = null) {
        $query = "SELECT valor FROM configuraciones WHERE clave = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$key]);
        
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return $row['valor'];
        }
        
        return $default;
    }
    
    // Guardar configuración
    public function setSetting($key, $value) {
        $query = "INSERT INTO configuraciones (clave, valor) 
                  VALUES (?, ?) 
                  ON DUPLICATE KEY UPDATE valor = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$key, $value, $value]);
    }
    
    // Obtener todas las configuraciones
    public function getAllSettings() {
        $query = "SELECT * FROM configuraciones ORDER BY clave";
        $stmt = $this->conn->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obtener estadísticas del sistema
    public function getSystemStats() {
        $stats = [];
        
        // Estudiantes por carrera
        $query = "SELECT c.nombre, COUNT(e.id) as total 
                  FROM carreras c 
                  LEFT JOIN estudiantes e ON c.id = e.carrera_id 
                  WHERE e.estado = 'activo'
                  GROUP BY c.id";
        $stmt = $this->conn->query($query);
        $stats['estudiantes_carrera'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ingresos por mes
        $query = "SELECT 
                    DATE_FORMAT(fecha_pago, '%Y-%m') as mes,
                    SUM(monto) as total
                  FROM pagos 
                  WHERE estado = 'pagado'
                  GROUP BY DATE_FORMAT(fecha_pago, '%Y-%m')
                  ORDER BY mes DESC 
                  LIMIT 6";
        $stmt = $this->conn->query($query);
        $stats['ingresos_mensuales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cursos más populares
        $query = "SELECT c.nombre, COUNT(m.id) as matriculados
                  FROM cursos c 
                  JOIN secciones s ON c.id = s.curso_id 
                  JOIN matriculas m ON s.id = m.seccion_id 
                  WHERE s.estado IN ('abierta', 'en_progreso')
                  GROUP BY c.id 
                  ORDER BY matriculados DESC 
                  LIMIT 10";
        $stmt = $this->conn->query($query);
        $stats['cursos_populares'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Docentes más activos
        $query = "SELECT d.nombres, d.apellidos, COUNT(s.id) as secciones
                  FROM docentes d 
                  JOIN secciones s ON d.id = s.docente_id 
                  WHERE s.estado IN ('abierta', 'en_progreso')
                  GROUP BY d.id 
                  ORDER BY secciones DESC 
                  LIMIT 10";
        $stmt = $this->conn->query($query);
        $stats['docentes_activos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    }
    
    // Obtener alertas del sistema
    public function getSystemAlerts() {
        $alerts = [];
        
        // Pagos vencidos
        $query = "SELECT COUNT(*) as total FROM pagos 
                  WHERE estado = 'pendiente' 
                  AND fecha_vencimiento < CURDATE()";
        $stmt = $this->conn->query($query);
        $vencidos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        if ($vencidos > 0) {
            $alerts[] = [
                'type' => 'danger',
                'title' => 'Pagos vencidos',
                'message' => "Hay $vencidos pagos vencidos que requieren atención"
            ];
        }
        
        // Secciones sin docente
        $query = "SELECT COUNT(*) as total FROM secciones 
                  WHERE docente_id IS NULL 
                  AND estado IN ('abierta', 'en_progreso')";
        $stmt = $this->conn->query($query);
        $sin_docente = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        if ($sin_docente > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Secciones sin docente',
                'message' => "Hay $sin_docente secciones sin docente asignado"
            ];
        }
        
        // Estudiantes sin matrícula
        $query = "SELECT COUNT(DISTINCT e.id) as total 
                  FROM estudiantes e 
                  LEFT JOIN matriculas m ON e.id = m.estudiante_id 
                  LEFT JOIN secciones s ON m.seccion_id = s.id 
                  WHERE e.estado = 'activo' 
                  AND s.periodo_academico = ? 
                  AND m.id IS NULL";
        $stmt = $this->conn->prepare($query);
        $periodo_actual = $this->getSetting('periodo_actual', '2024-1');
        $stmt->execute([$periodo_actual]);
        $sin_matricula = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        if ($sin_matricula > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Estudiantes sin matrícula',
                'message' => "Hay $sin_matricula estudiantes activos sin matrícula en el período actual"
            ];
        }
        
        return $alerts;
    }
    
    // Generar reporte del sistema
    public function generateSystemReport($type, $params = []) {
        $report = [];
        
        switch ($type) {
            case 'academic_performance':
                $report = $this->generateAcademicReport($params);
                break;
                
            case 'financial_summary':
                $report = $this->generateFinancialReport($params);
                break;
                
            case 'enrollment_analysis':
                $report = $this->generateEnrollmentReport($params);
                break;
                
            case 'faculty_performance':
                $report = $this->generateFacultyReport($params);
                break;
        }
        
        return $report;
    }
    
    private function generateAcademicReport($params) {
        // Implementar generación de reporte académico
        return [];
    }
    
    private function generateFinancialReport($params) {
        // Implementar generación de reporte financiero
        return [];
    }
    
    private function generateEnrollmentReport($params) {
        // Implementar generación de reporte de matrículas
        return [];
    }
    
    private function generateFacultyReport($params) {
        // Implementar generación de reporte docente
        return [];
    }
}
?>