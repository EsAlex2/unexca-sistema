<?php
// config/admin_settings.php

class AdminSettings
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    // Obtener configuración del sistema
    public function getSetting($key, $default = null)
    {
        $query = "SELECT valor FROM configuraciones WHERE clave = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$key]);

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return $row['valor'];
        }

        return $default;
    }

    // Guardar configuración
    public function setSetting($key, $value)
    {
        $query = "INSERT INTO configuraciones (clave, valor) 
                  VALUES (?, ?) 
                  ON DUPLICATE KEY UPDATE valor = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$key, $value, $value]);
    }

    // Obtener todas las configuraciones
    public function getAllSettings()
    {
        $query = "SELECT * FROM configuraciones ORDER BY clave";
        $stmt = $this->conn->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener estadísticas del sistema
    public function getSystemStats()
    {
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
    public function getSystemAlerts()
    {
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
    public function generateSystemReport($type, $params = [])
    {
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

    private function generateAcademicReport($params)
    {
        // Implementar generación de reporte académico
        return [];
    }

    private function generateFinancialReport($params)
    {
        // Implementar generación de reporte financiero
        return [];
    }

    private function generateEnrollmentReport($params)
    {
        // Implementar generación de reporte de matrículas
        return [];
    }

    private function generateFacultyReport($params)
    {
        // Implementar generación de reporte docente
        return [];
    }

    // Verificar si el sistema necesita mantenimiento
    public function checkMaintenanceNeeded()
    {
        $issues = [];

        // Verificar backups recientes
        $query = "SELECT COUNT(*) as total FROM backups 
                  WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $stmt = $this->conn->query($query);
        $recent_backups = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        if ($recent_backups == 0) {
            $issues[] = [
                'type' => 'warning',
                'title' => 'Sin backups recientes',
                'message' => 'No se han realizado backups en los últimos 7 días'
            ];
        }

        // Verificar espacio en disco
        $free_space = disk_free_space('/');
        $total_space = disk_total_space('/');
        $percent_free = ($free_space / $total_space) * 100;

        if ($percent_free < 10) {
            $issues[] = [
                'type' => 'danger',
                'title' => 'Espacio en disco bajo',
                'message' => 'Solo queda el ' . round($percent_free, 2) . '% de espacio libre'
            ];
        }

        // Verificar logs de error
        $query = "SELECT COUNT(*) as total FROM logs_sistema 
                  WHERE nivel = 'error' 
                  AND fecha >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
        $stmt = $this->conn->query($query);
        $recent_errors = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        if ($recent_errors > 10) {
            $issues[] = [
                'type' => 'danger',
                'title' => 'Errores en el sistema',
                'message' => $recent_errors . ' errores registrados en las últimas 24 horas'
            ];
        }

        return $issues;
    }

    // Obtener configuración agrupada por categoría
    public function getSettingsByCategory()
    {
        $query = "SELECT * FROM configuraciones ORDER BY clave";
        $stmt = $this->conn->query($query);
        $all_settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $categorized = [
            'general' => [],
            'academico' => [],
            'financiero' => [],
            'correo' => [],
            'seguridad' => [],
            'backup' => []
        ];

        foreach ($all_settings as $setting) {
            $key = $setting['clave'];

            if (
                strpos($key, 'nombre_') === 0 || strpos($key, 'logo_') === 0 ||
                strpos($key, 'periodo_') === 0 || strpos($key, 'moneda') === 0 ||
                strpos($key, 'zona_horaria') === 0
            ) {
                $categorized['general'][$key] = $setting['valor'];
            } elseif (
                strpos($key, 'nota_') === 0 || strpos($key, 'maximo_creditos') === 0 ||
                strpos($key, 'fecha_') === 0 && (strpos($key, 'matricula') !== false)
            ) {
                $categorized['academico'][$key] = $setting['valor'];
            } elseif (
                strpos($key, 'monto_') === 0 || strpos($key, 'dias_vencimiento') === 0 ||
                strpos($key, 'porcentaje_') === 0
            ) {
                $categorized['financiero'][$key] = $setting['valor'];
            } elseif (
                strpos($key, 'smtp_') === 0 || strpos($key, 'email_') === 0 ||
                strpos($key, 'notificar_') === 0
            ) {
                $categorized['correo'][$key] = $setting['valor'];
            } elseif (
                strpos($key, 'max_intentos') === 0 || strpos($key, 'tiempo_bloqueo') === 0 ||
                strpos($key, 'requerir_') === 0 || strpos($key, 'ssl_') === 0
            ) {
                $categorized['seguridad'][$key] = $setting['valor'];
            } elseif (
                strpos($key, 'auto_backup') === 0 || strpos($key, 'frecuencia_backup') === 0 ||
                strpos($key, 'mantener_') === 0
            ) {
                $categorized['backup'][$key] = $setting['valor'];
            } else {
                $categorized['general'][$key] = $setting['valor'];
            }
        }

        return $categorized;
    }

    // Restablecer configuración a valores por defecto
    public function resetSettings($category)
    {
        $defaults = $this->getDefaultSettings($category);
        $success_count = 0;

        foreach ($defaults as $key => $value) {
            if ($this->setSetting($key, $value)) {
                $success_count++;
            }
        }

        return $success_count;
    }

    // Obtener valores por defecto por categoría
    private function getDefaultSettings($category)
    {
        $defaults = [
            'general' => [
                'nombre_institucion' => 'UNEXCA',
                'periodo_actual' => date('Y') . '-1',
                'moneda' => 'USD',
                'zona_horaria' => 'America/Caracas'
            ],
            'academico' => [
                'nota_minima' => '10',
                'nota_excelencia' => '16',
                'maximo_creditos' => '24'
            ],
            'financiero' => [
                'monto_matricula' => '500',
                'monto_mensualidad' => '300',
                'dias_vencimiento' => '30',
                'porcentaje_mora' => '0.5'
            ],
            'correo' => [
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => '587',
                'email_from' => 'noreply@unexca.edu',
                'notificar_nuevos_usuarios' => '1'
            ],
            'seguridad' => [
                'max_intentos_login' => '3',
                'tiempo_bloqueo' => '30',
                'requerir_cambio_password' => '90',
                'ssl_requerido' => '0'
            ],
            'backup' => [
                'auto_backup' => '1',
                'frecuencia_backup' => 'daily',
                'mantener_backups' => '30',
                'notificar_backup' => '1'
            ]
        ];

        return $defaults[$category] ?? [];
    }
}
?>
