-- Base de datos UNEXCA
CREATE DATABASE IF NOT EXISTS unexca;
USE unexca;

-- Tabla de usuarios
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    rol ENUM('estudiante', 'docente', 'administrador', 'padre') NOT NULL,
    estado ENUM('activo', 'inactivo', 'pendiente') DEFAULT 'activo',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_login DATETIME
);

-- Tabla de estudiantes
CREATE TABLE estudiantes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT UNIQUE,
    codigo_estudiante VARCHAR(20) UNIQUE NOT NULL,
    cedula VARCHAR(20) UNIQUE NOT NULL,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    fecha_nacimiento DATE,
    genero ENUM('M', 'F', 'O'),
    telefono VARCHAR(20),
    direccion TEXT,
    ciudad VARCHAR(100),
    estado_civil VARCHAR(50),
    fecha_ingreso DATE,
    carrera_id INT,
    semestre_actual INT,
    creditos_aprobados INT DEFAULT 0,
    promedio_general DECIMAL(4,2) DEFAULT 0.00,
    estado ENUM('activo', 'inactivo', 'pendiente') DEFAULT 'activo',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabla de docentes
CREATE TABLE docentes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT UNIQUE,
    codigo_docente VARCHAR(20) UNIQUE NOT NULL,
    cedula VARCHAR(20) UNIQUE NOT NULL,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    titulo_academico VARCHAR(100),
    especialidad VARCHAR(100),
    telefono VARCHAR(20),
    email VARCHAR(100),
    fecha_contratacion DATE,
    departamento_id INT,
    estado ENUM('activo', 'inactivo', 'licencia') DEFAULT 'activo',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabla de carreras
CREATE TABLE carreras (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codigo VARCHAR(20) UNIQUE NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    duracion_semestres INT,
    creditos_totales INT,
    facultad VARCHAR(100),
    coordinador_id INT,
    estado ENUM('activa', 'inactiva') DEFAULT 'activa'
);

-- Tabla de cursos
CREATE TABLE cursos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codigo VARCHAR(20) UNIQUE NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    creditos INT NOT NULL,
    semestre INT,
    carrera_id INT,
    prerequisito_id INT,
    horas_teoria INT,
    horas_practica INT,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    FOREIGN KEY (carrera_id) REFERENCES carreras(id),
    FOREIGN KEY (prerequisito_id) REFERENCES cursos(id)
);

-- Tabla de secciones (clases)
CREATE TABLE secciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    curso_id INT NOT NULL,
    docente_id INT NOT NULL,
    codigo_seccion VARCHAR(20) UNIQUE NOT NULL,
    periodo_academico VARCHAR(50),
    horario TEXT,
    aula VARCHAR(50),
    cupo_maximo INT,
    cupo_actual INT DEFAULT 0,
    fecha_inicio DATE,
    fecha_fin DATE,
    estado ENUM('abierta', 'cerrada', 'en_progreso', 'finalizada') DEFAULT 'abierta',
    FOREIGN KEY (curso_id) REFERENCES cursos(id),
    FOREIGN KEY (docente_id) REFERENCES docentes(id)
);

-- Tabla de matrículas
CREATE TABLE matriculas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    estudiante_id INT NOT NULL,
    seccion_id INT NOT NULL,
    fecha_matricula TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('matriculado', 'retirado', 'aprobado', 'reprobado') DEFAULT 'matriculado',
    nota_final DECIMAL(4,2),
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id),
    FOREIGN KEY (seccion_id) REFERENCES secciones(id),
    UNIQUE KEY unique_matricula (estudiante_id, seccion_id)
);

-- Tabla de tipos de evaluación
CREATE TABLE tipos_evaluacion (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    peso DECIMAL(5,2) NOT NULL,
    seccion_id INT NOT NULL,
    orden INT,
    FOREIGN KEY (seccion_id) REFERENCES secciones(id)
);

-- Tabla de calificaciones
CREATE TABLE calificaciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    matricula_id INT NOT NULL,
    tipo_evaluacion_id INT NOT NULL,
    nota DECIMAL(5,2) NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    comentario TEXT,
    FOREIGN KEY (matricula_id) REFERENCES matriculas(id),
    FOREIGN KEY (tipo_evaluacion_id) REFERENCES tipos_evaluacion(id)
);

-- Tabla de pagos
CREATE TABLE pagos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    estudiante_id INT NOT NULL,
    concepto VARCHAR(200) NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    fecha_vencimiento DATE,
    fecha_pago DATE,
    estado ENUM('pendiente', 'pagado', 'vencido', 'cancelado') DEFAULT 'pendiente',
    metodo_pago VARCHAR(50),
    referencia VARCHAR(100),
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id)
);

-- Tabla de mensajes
CREATE TABLE mensajes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    remitente_id INT NOT NULL,
    destinatario_id INT NOT NULL,
    asunto VARCHAR(200),
    contenido TEXT NOT NULL,
    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    leido BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (remitente_id) REFERENCES usuarios(id),
    FOREIGN KEY (destinatario_id) REFERENCES usuarios(id)
);

    -- Tabla de notificaciones
CREATE TABLE notificaciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    tipo VARCHAR(50),
    titulo VARCHAR(200),
    mensaje TEXT,
    url VARCHAR(500),
    leido BOOLEAN DEFAULT FALSE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
     FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabla de solicitudes
CREATE TABLE solicitudes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo VARCHAR(100) NOT NULL,
    estudiante_id INT,
    docente_id INT,
    descripcion TEXT NOT NULL,
    fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion DATETIME,
    estado ENUM('pendiente', 'aprobada', 'rechazada', 'en_proceso') DEFAULT 'pendiente',
    respuesta TEXT,
    administrador_id INT,
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id),
    FOREIGN KEY (docente_id) REFERENCES docentes(id),
    FOREIGN KEY (administrador_id) REFERENCES usuarios(id)
);

-- Insertar algunas carreras de ejemplo
INSERT INTO carreras (codigo, nombre, descripcion, duracion_semestres, creditos_totales, facultad) VALUES
('ING-SIS', 'Ingeniería en Sistemas', 'Carrera de ingeniería en sistemas computacionales', 10, 180, 'Ingeniería'),
('LIC-ADM', 'Licenciatura en Administración', 'Carrera de administración de empresas', 8, 160, 'Ciencias Económicas'),
('DERECHO', 'Derecho', 'Carrera de derecho y ciencias jurídicas', 10, 180, 'Ciencias Jurídicas');