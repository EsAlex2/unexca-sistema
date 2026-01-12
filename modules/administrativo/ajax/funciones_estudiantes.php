<?php
// modules/administrativo/ajax/funciones_estudiantes.php

function generarCodigoEstudiante($conn, $carrera_id) {
    // Obtener código de carrera
    $query = "SELECT codigo FROM carreras WHERE id = :carrera_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':carrera_id', $carrera_id);
    $stmt->execute();
    $carrera = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$carrera) {
        return 'EST-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    // Contar estudiantes en esta carrera
    $query = "SELECT COUNT(*) as total FROM estudiantes WHERE carrera_id = :carrera_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':carrera_id', $carrera_id);
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $codigo_carrera = substr($carrera['codigo'], 0, 3);
    $anio = date('y'); // Últimos 2 dígitos del año
    $secuencia = str_pad($total + 1, 4, '0', STR_PAD_LEFT);
    
    return strtoupper($codigo_carrera) . $anio . $secuencia;
}

function validarEstudiante($datos) {
    $errores = [];
    
    // Validar cédula
    if (empty($datos['cedula']) || strlen($datos['cedula']) < 6) {
        $errores[] = 'La cédula debe tener al menos 6 caracteres';
    }
    
    // Validar email
    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El email no es válido';
    }
    
    // Validar fecha de nacimiento
    $fecha_nacimiento = DateTime::createFromFormat('Y-m-d', $datos['fecha_nacimiento']);
    if (!$fecha_nacimiento) {
        $errores[] = 'Fecha de nacimiento inválida';
    } else {
        $hoy = new DateTime();
        $edad = $hoy->diff($fecha_nacimiento)->y;
        if ($edad < 15) {
            $errores[] = 'El estudiante debe tener al menos 15 años';
        }
    }
    
    // Validar semestre
    if (!is_numeric($datos['semestre_actual']) || $datos['semestre_actual'] < 1 || $datos['semestre_actual'] > 10) {
        $errores[] = 'El semestre debe estar entre 1 y 10';
    }
    
    return $errores;
}

function calcularPromedioEstudiante($conn, $estudiante_id) {
    $query = "SELECT AVG(nota_final) as promedio 
              FROM matriculas 
              WHERE estudiante_id = :estudiante_id 
              AND nota_final IS NOT NULL";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':estudiante_id', $estudiante_id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['promedio'] ? number_format($result['promedio'], 2) : 'N/A';
}

function obtenerEstadisticasCarrera($conn, $carrera_id) {
    $query = "SELECT 
                COUNT(*) as total_estudiantes,
                AVG(promedio_general) as promedio_carrera,
                COUNT(CASE WHEN estado = 'activo' THEN 1 END) as activos,
                COUNT(CASE WHEN estado = 'graduado' THEN 1 END) as graduados,
                COUNT(CASE WHEN estado = 'egresado' THEN 1 END) as egresados
              FROM estudiantes 
              WHERE carrera_id = :carrera_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':carrera_id', $carrera_id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>