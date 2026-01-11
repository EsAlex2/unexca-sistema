<?php
// config/constants.php

define('SITE_NAME', 'UNEXCA - Sistema de Gestión Académica');
define('SITE_URL', 'http://localhost/unexca-sistema/');

// Roles del sistema
define('ROL_ESTUDIANTE', 'estudiante');
define('ROL_DOCENTE', 'docente');
define('ROL_ADMIN', 'administrador');
define('ROL_PADRE', 'padre');

// Estados
define('ESTADO_ACTIVO', 'activo');
define('ESTADO_INACTIVO', 'inactivo');
define('ESTADO_PENDIENTE', 'pendiente');

// Rutas de los módulos
define('MODULO_ESTUDIANTES', 'modules/estudiantes/');
define('MODULO_DOCENTES', 'modules/docentes/');
define('MODULO_CALIFICACIONES', 'modules/calificaciones/');
define('MODULO_ADMIN', 'modules/administrativo/');
define('MODULO_REPORTES', 'modules/reportes/');
define('MODULO_COMUNICACION', 'modules/comunicacion/');
?>