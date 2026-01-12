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
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_estudiante VARCHAR(20) UNIQUE NOT NULL,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    cedula VARCHAR(20) UNIQUE NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    genero ENUM('M', 'F', 'O') NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    telefono VARCHAR(20),
    direccion TEXT,
    carrera_id INT NOT NULL,
    semestre_actual INT DEFAULT 1,
    promedio_general DECIMAL(4,2),
    fecha_ingreso DATE NOT NULL,
    fecha_egreso DATE,
    estado ENUM('activo', 'inactivo', 'egresado', 'graduado', 'suspendido') DEFAULT 'activo',
    usuario_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (carrera_id) REFERENCES carreras(id) ON DELETE RESTRICT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Tabla de matrículas
CREATE TABLE matriculas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    seccion_id INT NOT NULL,
    fecha_matricula DATE NOT NULL,
    nota_final DECIMAL(4,2),
    estado ENUM('matriculado', 'aprobado', 'reprobado', 'retirado') DEFAULT 'matriculado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE,
    FOREIGN KEY (seccion_id) REFERENCES secciones(id) ON DELETE CASCADE,
    UNIQUE KEY unique_matricula (estudiante_id, seccion_id)
);

-- Tabla de documentos de estudiantes
CREATE TABLE documentos_estudiantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    tipo ENUM('academico', 'personal', 'legal', 'otros') NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    tamano VARCHAR(50),
    estado ENUM('pendiente', 'aprobado', 'rechazado') DEFAULT 'pendiente',
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observaciones TEXT,
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE
);

-- Índices para mejorar el rendimiento
CREATE INDEX idx_estudiantes_carrera ON estudiantes(carrera_id);
CREATE INDEX idx_estudiantes_estado ON estudiantes(estado);
CREATE INDEX idx_matriculas_estudiante ON matriculas(estudiante_id);
CREATE INDEX idx_matriculas_seccion ON matriculas(seccion_id);
CREATE INDEX idx_documentos_estudiante ON documentos_estudiantes(estudiante_id);

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

-- Tabla de departamentos
CREATE TABLE departamentos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codigo VARCHAR(20) UNIQUE NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    director_id INT,
    telefono VARCHAR(20),
    email VARCHAR(100),
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (director_id) REFERENCES docentes(id)
);

-- Tabla de configuraciones
CREATE TABLE IF NOT EXISTS configuraciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT,
    descripcion TEXT,
    categoria VARCHAR(50),
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de logs de correo
CREATE TABLE IF NOT EXISTS logs_correo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    asunto VARCHAR(255),
    estado ENUM('enviado', 'fallo', 'pendiente') DEFAULT 'pendiente',
    error TEXT,
    fecha_envio DATETIME,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insertar algunas carreras de ejemplo
INSERT INTO carreras (codigo, nombre, descripcion, duracion_semestres, creditos_totales, facultad) VALUES
('ING-SIS', 'Ingeniería en Sistemas', 'Carrera de ingeniería en sistemas computacionales', 10, 180, 'Ingeniería'),
('LIC-ADM', 'Licenciatura en Administración', 'Carrera de administración de empresas', 8, 160, 'Ciencias Económicas'),
('DERECHO', 'Derecho', 'Carrera de derecho y ciencias jurídicas', 10, 180, 'Ciencias Jurídicas');

INSERT INTO departamentos (codigo, nombre, descripcion) VALUES
('DEP-MAT', 'Departamento de Matemáticas', 'Departamento de ciencias matemáticas'),
('DEP-FIS', 'Departamento de Física', 'Departamento de ciencias físicas'),
('DEP-INF', 'Departamento de Informática', 'Departamento de ciencias de la computación'),
('DEP-ADM', 'Departamento de Administración', 'Departamento de ciencias administrativas'),
('DEP-DER', 'Departamento de Derecho', 'Departamento de ciencias jurídicas');

-- Insertar configuraciones por defecto
INSERT INTO configuraciones (clave, valor, descripcion, categoria) VALUES
('nombre_institucion', 'UNEXCA', 'Nombre de la institución', 'general'),
('periodo_actual', '2024-1', 'Período académico actual', 'general'),
('moneda', 'Bs', 'Moneda principal del sistema', 'general'),
('zona_horaria', 'America/Caracas', 'Zona horaria del sistema', 'general'),
('nota_minima', '10', 'Nota mínima para aprobar', 'academico'),
('nota_excelencia', '16', 'Nota para excelencia académica', 'academico'),
('maximo_creditos', '24', 'Máximo de créditos por semestre', 'academico'),
('monto_matricula', '500', 'Monto de matrícula', 'financiero'),
('monto_mensualidad', '300', 'Monto de mensualidad', 'financiero'),
('dias_vencimiento', '30', 'Días para vencimiento de pagos', 'financiero'),
('porcentaje_mora', '0.5', 'Porcentaje de mora diaria', 'financiero'),
('smtp_host', 'smtp.gmail.com', 'Servidor SMTP', 'correo'),
('smtp_port', '587', 'Puerto SMTP', 'correo'),
('email_from', 'noreply@unexca.edu', 'Email remitente', 'correo'),
('max_intentos_login', '3', 'Máximo de intentos de login', 'seguridad'),
('tiempo_bloqueo', '30', 'Minutos de bloqueo tras intentos fallidos', 'seguridad'),
('requerir_cambio_password', '90', 'Días para forzar cambio de contraseña', 'seguridad'),
('auto_backup', '1', 'Activar backup automático', 'backup'),
('frecuencia_backup', 'daily', 'Frecuencia de backups automáticos', 'backup'),
('mantener_backups', '30', 'Días para mantener backups', 'backup');