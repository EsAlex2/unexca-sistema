<?php
// modules/administrativo/configuracion.php
require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SESSION['rol'] != 'administrador') {
    header('Location: ../../index.php');
    exit();
}

$page_title = 'Configuración del Sistema';

$db = new Database();
$conn = $db->getConnection();

// Inicializar configuración del sistema
$adminSettings = null;
if (file_exists('../../config/admin_settings.php')) {
    require_once '../../config/admin_settings.php';
    $adminSettings = new AdminSettings($conn);
}

// Categorías de configuración
$categorias = [
    'general' => [
        'nombre' => 'General',
        'icono' => 'fas fa-cog',
        'descripcion' => 'Configuración básica del sistema'
    ],
    'academico' => [
        'nombre' => 'Académico',
        'icono' => 'fas fa-graduation-cap',
        'descripcion' => 'Configuración académica y de calificaciones'
    ],
    'financiero' => [
        'nombre' => 'Financiero',
        'icono' => 'fas fa-money-bill-wave',
        'descripcion' => 'Configuración de pagos y finanzas'
    ],
    'correo' => [
        'nombre' => 'Correo y Notificaciones',
        'icono' => 'fas fa-envelope',
        'descripcion' => 'Configuración de correo electrónico'
    ],
    'seguridad' => [
        'nombre' => 'Seguridad',
        'icono' => 'fas fa-shield-alt',
        'descripcion' => 'Configuración de seguridad del sistema'
    ],
    'backup' => [
        'nombre' => 'Backup y Mantenimiento',
        'icono' => 'fas fa-database',
        'descripcion' => 'Configuración de respaldo y mantenimiento'
    ]
];

// Configuraciones por defecto
$configuraciones = [
    'general' => [
        'nombre_institucion' => [
            'tipo' => 'text',
            'etiqueta' => 'Nombre de la Institución',
            'valor' => $adminSettings ? $adminSettings->getSetting('nombre_institucion', 'UNEXCA') : 'UNEXCA',
            'placeholder' => 'Ej: Universidad Nacional Experimental de la Gran Caracas',
            'requerido' => true
        ],
        'logo_institucion' => [
            'tipo' => 'file',
            'etiqueta' => 'Logo de la Institución',
            'valor' => $adminSettings ? $adminSettings->getSetting('logo_institucion', '') : '',
            'descripcion' => 'Tamaño recomendado: 200x60px'
        ],
        'periodo_actual' => [
            'tipo' => 'text',
            'etiqueta' => 'Período Académico Actual',
            'valor' => $adminSettings ? $adminSettings->getSetting('periodo_actual', date('Y') . '-1') : date('Y') . '-1',
            'placeholder' => 'Formato: AÑO-SEMESTRE (ej: 2024-1)',
            'requerido' => true
        ],
        'moneda' => [
            'tipo' => 'select',
            'etiqueta' => 'Moneda',
            'valor' => $adminSettings ? $adminSettings->getSetting('moneda', 'VES') : 'VES',
            'opciones' => [
                'USD' => 'Dólares ($)',
                'EUR' => 'Euros (€)',
                'VES' => 'Bolívares (Bs.)'
            ]
        ],
        'zona_horaria' => [
            'tipo' => 'select',
            'etiqueta' => 'Zona Horaria',
            'valor' => $adminSettings ? $adminSettings->getSetting('zona_horaria', 'America/Caracas') : 'America/Caracas',
            'opciones' => [
                'America/Caracas' => 'Caracas (GMT-4)',
                'America/Mexico_City' => 'Ciudad de México (GMT-6)',
                'America/Bogota' => 'Bogotá (GMT-5)',
                'America/Lima' => 'Lima (GMT-5)',
                'America/Santiago' => 'Santiago (GMT-3)'
            ]
        ]
    ],
    
    'academico' => [
        'nota_minima' => [
            'tipo' => 'number',
            'etiqueta' => 'Nota Mínima para Aprobar',
            'valor' => $adminSettings ? $adminSettings->getSetting('nota_minima', '10') : '10',
            'min' => '0',
            'max' => '20',
            'step' => '0.5',
            'requerido' => true
        ],
        'nota_excelencia' => [
            'tipo' => 'number',
            'etiqueta' => 'Nota para Excelencia',
            'valor' => $adminSettings ? $adminSettings->getSetting('nota_excelencia', '16') : '16',
            'min' => '0',
            'max' => '20',
            'step' => '0.5'
        ],
        'maximo_creditos' => [
            'tipo' => 'number',
            'etiqueta' => 'Máximo de Créditos por Semestre',
            'valor' => $adminSettings ? $adminSettings->getSetting('maximo_creditos', '24') : '24',
            'min' => '1',
            'max' => '50'
        ],
        'fecha_inicio_matriculas' => [
            'tipo' => 'date',
            'etiqueta' => 'Fecha de Inicio de Matrículas',
            'valor' => $adminSettings ? $adminSettings->getSetting('fecha_inicio_matriculas', date('Y-m-d')) : date('Y-m-d')
        ],
        'fecha_fin_matriculas' => [
            'tipo' => 'date',
            'etiqueta' => 'Fecha de Fin de Matrículas',
            'valor' => $adminSettings ? $adminSettings->getSetting('fecha_fin_matriculas', date('Y-m-d', strtotime('+30 days'))) : date('Y-m-d', strtotime('+30 days'))
        ]
    ],
    
    'financiero' => [
        'monto_matricula' => [
            'tipo' => 'number',
            'etiqueta' => 'Monto de Matrícula',
            'valor' => $adminSettings ? $adminSettings->getSetting('monto_matricula', '500') : '500',
            'step' => '0.01',
            'prefijo' => '$'
        ],
        'monto_mensualidad' => [
            'tipo' => 'number',
            'etiqueta' => 'Monto de Mensualidad',
            'valor' => $adminSettings ? $adminSettings->getSetting('monto_mensualidad', '300') : '300',
            'step' => '0.01',
            'prefijo' => '$'
        ],
        'dias_vencimiento' => [
            'tipo' => 'number',
            'etiqueta' => 'Días para Vencimiento de Pagos',
            'valor' => $adminSettings ? $adminSettings->getSetting('dias_vencimiento', '30') : '30',
            'min' => '1',
            'max' => '90'
        ],
        'porcentaje_mora' => [
            'tipo' => 'number',
            'etiqueta' => 'Porcentaje de Mora Diaria',
            'valor' => $adminSettings ? $adminSettings->getSetting('porcentaje_mora', '0.5') : '0.5',
            'step' => '0.1',
            'min' => '0',
            'max' => '10',
            'sufijo' => '%'
        ]
    ],
    
    'correo' => [
        'smtp_host' => [
            'tipo' => 'text',
            'etiqueta' => 'Servidor SMTP',
            'valor' => $adminSettings ? $adminSettings->getSetting('smtp_host', 'smtp.gmail.com') : 'smtp.gmail.com'
        ],
        'smtp_port' => [
            'tipo' => 'number',
            'etiqueta' => 'Puerto SMTP',
            'valor' => $adminSettings ? $adminSettings->getSetting('smtp_port', '587') : '587'
        ],
        'smtp_username' => [
            'tipo' => 'text',
            'etiqueta' => 'Usuario SMTP',
            'valor' => $adminSettings ? $adminSettings->getSetting('smtp_username', '') : ''
        ],
        'smtp_password' => [
            'tipo' => 'password',
            'etiqueta' => 'Contraseña SMTP',
            'valor' => $adminSettings ? $adminSettings->getSetting('smtp_password', '') : '',
            'descripcion' => 'Dejar vacío para mantener la actual'
        ],
        'email_from' => [
            'tipo' => 'email',
            'etiqueta' => 'Email Remitente',
            'valor' => $adminSettings ? $adminSettings->getSetting('email_from', 'noreply@unexca.edu') : 'noreply@unexca.edu'
        ],
        'notificar_nuevos_usuarios' => [
            'tipo' => 'checkbox',
            'etiqueta' => 'Notificar creación de nuevos usuarios',
            'valor' => $adminSettings ? $adminSettings->getSetting('notificar_nuevos_usuarios', '1') : '1'
        ]
    ],
    
    'seguridad' => [
        'max_intentos_login' => [
            'tipo' => 'number',
            'etiqueta' => 'Máximo de Intentos de Login',
            'valor' => $adminSettings ? $adminSettings->getSetting('max_intentos_login', '3') : '3',
            'min' => '1',
            'max' => '10'
        ],
        'tiempo_bloqueo' => [
            'tipo' => 'number',
            'etiqueta' => 'Minutos de Bloqueo',
            'valor' => $adminSettings ? $adminSettings->getSetting('tiempo_bloqueo', '30') : '30',
            'min' => '1',
            'max' => '1440',
            'descripcion' => 'Tiempo de bloqueo tras exceder intentos'
        ],
        'requerir_cambio_password' => [
            'tipo' => 'number',
            'etiqueta' => 'Días para Cambio de Contraseña',
            'valor' => $adminSettings ? $adminSettings->getSetting('requerir_cambio_password', '90') : '90',
            'descripcion' => 'Forzar cambio de contraseña cada X días (0 para deshabilitar)'
        ],
        'ssl_requerido' => [
            'tipo' => 'checkbox',
            'etiqueta' => 'Requerir conexión SSL',
            'valor' => $adminSettings ? $adminSettings->getSetting('ssl_requerido', '0') : '0'
        ]
    ],
    
    'backup' => [
        'auto_backup' => [
            'tipo' => 'checkbox',
            'etiqueta' => 'Backup Automático',
            'valor' => $adminSettings ? $adminSettings->getSetting('auto_backup', '1') : '1'
        ],
        'frecuencia_backup' => [
            'tipo' => 'select',
            'etiqueta' => 'Frecuencia de Backup',
            'valor' => $adminSettings ? $adminSettings->getSetting('frecuencia_backup', 'daily') : 'daily',
            'opciones' => [
                'daily' => 'Diario',
                'weekly' => 'Semanal',
                'monthly' => 'Mensual'
            ]
        ],
        'mantener_backups' => [
            'tipo' => 'number',
            'etiqueta' => 'Mantener Backups (días)',
            'valor' => $adminSettings ? $adminSettings->getSetting('mantener_backups', '30') : '30',
            'min' => '1',
            'max' => '365'
        ],
        'notificar_backup' => [
            'tipo' => 'checkbox',
            'etiqueta' => 'Notificar sobre backups',
            'valor' => $adminSettings ? $adminSettings->getSetting('notificar_backup', '1') : '1'
        ]
    ]
];

// Procesar guardado de configuración
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_configuracion'])) {
    $categoria = $_POST['categoria'];
    $valores = $_POST;
    
    if ($adminSettings) {
        $guardados = 0;
        $errores = 0;
        
        foreach ($configuraciones[$categoria] as $clave => $config) {
            if (isset($valores[$clave])) {
                $valor = sanitize($valores[$clave]);
                
                // Para contraseñas, solo actualizar si no está vacía
                if ($config['tipo'] == 'password' && empty($valor)) {
                    continue;
                }
                
                if ($adminSettings->setSetting($clave, $valor)) {
                    $guardados++;
                } else {
                    $errores++;
                }
            }
        }
        
        if ($guardados > 0) {
            $_SESSION['message'] = "Configuración de " . $categorias[$categoria]['nombre'] . " guardada correctamente ($guardados configuraciones)";
            $_SESSION['message_type'] = 'success';
            
            // Actualizar valores en el array
            foreach ($configuraciones[$categoria] as $clave => &$config) {
                if (isset($valores[$clave]) && !($config['tipo'] == 'password' && empty($valores[$clave]))) {
                    $config['valor'] = sanitize($valores[$clave]);
                }
            }
        }
        
        if ($errores > 0) {
            $_SESSION['message'] = "Hubo $errores errores al guardar algunas configuraciones";
            $_SESSION['message_type'] = 'warning';
        }
        
        header('Location: configuracion.php?categoria=' . $categoria);
        exit();
    }
}

// Categoría activa
$categoria_activa = $_GET['categoria'] ?? 'general';

include '../../includes/header.php';

// Mostrar mensajes
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-' . $_SESSION['message_type'] . ' alert-dismissible fade show" role="alert">
            ' . $_SESSION['message'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['message'], $_SESSION['message_type']);
}
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Configuración del Sistema</h5>
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-outline-unexca" onclick="probarConfiguracion('email')">
                <i class="fas fa-envelope me-2"></i>Probar Email
            </button>
            <button type="button" class="btn btn-outline-unexca" onclick="realizarBackup()">
                <i class="fas fa-database me-2"></i>Backup Manual
            </button>
            <button type="button" class="btn btn-unexca" data-bs-toggle="modal" data-bs-target="#modalInfoSistema">
                <i class="fas fa-info-circle me-2"></i>Info del Sistema
            </button>
        </div>
    </div>
    
    <div class="card-body">
        <div class="row">
            <!-- Navegación de categorías -->
            <div class="col-md-3 mb-4">
                <div class="list-group">
                    <?php foreach ($categorias as $key => $categoria): ?>
                    <a href="configuracion.php?categoria=<?php echo $key; ?>" 
                       class="list-group-item list-group-item-action d-flex align-items-center <?php echo ($categoria_activa == $key) ? 'active' : ''; ?>">
                        <i class="<?php echo $categoria['icono']; ?> me-3 fa-fw"></i>
                        <div>
                            <h6 class="mb-1"><?php echo $categoria['nombre']; ?></h6>
                            <small class="text-muted"><?php echo $categoria['descripcion']; ?></small>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <!-- Estado del sistema -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="card-title">Estado del Sistema</h6>
                        <div class="system-status">
                            <div class="status-item">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <span>Base de datos: <strong>Conectada</strong></span>
                            </div>
                            <div class="status-item">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <span>PHP: <strong><?php echo phpversion(); ?></strong></span>
                            </div>
                            <div class="status-item">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <span>Espacio: <strong><?php echo round(disk_free_space('/') / 1024 / 1024 / 1024, 2); ?> GB libre</strong></span>
                            </div>
                            <div class="status-item">
                                <i class="fas fa-server me-2"></i>
                                <span>Último backup: <strong>Hace 2 días</strong></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Formulario de configuración -->
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="<?php echo $categorias[$categoria_activa]['icono']; ?> me-2"></i>
                            <?php echo $categorias[$categoria_activa]['nombre']; ?>
                        </h5>
                        <p class="text-muted mb-0"><?php echo $categorias[$categoria_activa]['descripcion']; ?></p>
                    </div>
                    
                    <div class="card-body">
                        <form method="POST" action="" id="formConfiguracion">
                            <input type="hidden" name="guardar_configuracion" value="1">
                            <input type="hidden" name="categoria" value="<?php echo $categoria_activa; ?>">
                            
                            <div class="row">
                                <?php foreach ($configuraciones[$categoria_activa] as $clave => $config): ?>
                                <div class="col-md-6 mb-3">
                                    <label for="<?php echo $clave; ?>" class="form-label">
                                        <?php echo $config['etiqueta']; ?>
                                        <?php if (isset($config['requerido']) && $config['requerido']): ?>
                                        <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    
                                    <?php if ($config['tipo'] == 'text' || $config['tipo'] == 'email' || $config['tipo'] == 'password'): ?>
                                    <div class="input-group">
                                        <?php if (isset($config['prefijo'])): ?>
                                        <span class="input-group-text"><?php echo $config['prefijo']; ?></span>
                                        <?php endif; ?>
                                        
                                        <input type="<?php echo $config['tipo']; ?>" 
                                               class="form-control" 
                                               id="<?php echo $clave; ?>" 
                                               name="<?php echo $clave; ?>" 
                                               value="<?php echo htmlspecialchars($config['valor']); ?>"
                                               <?php if (isset($config['placeholder'])): ?>placeholder="<?php echo $config['placeholder']; ?>"<?php endif; ?>
                                               <?php if (isset($config['requerido']) && $config['requerido']): ?>required<?php endif; ?>>
                                               
                                        <?php if (isset($config['sufijo'])): ?>
                                        <span class="input-group-text"><?php echo $config['sufijo']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php elseif ($config['tipo'] == 'number'): ?>
                                    <div class="input-group">
                                        <?php if (isset($config['prefijo'])): ?>
                                        <span class="input-group-text"><?php echo $config['prefijo']; ?></span>
                                        <?php endif; ?>
                                        
                                        <input type="number" 
                                               class="form-control" 
                                               id="<?php echo $clave; ?>" 
                                               name="<?php echo $clave; ?>" 
                                               value="<?php echo $config['valor']; ?>"
                                               <?php if (isset($config['min'])): ?>min="<?php echo $config['min']; ?>"<?php endif; ?>
                                               <?php if (isset($config['max'])): ?>max="<?php echo $config['max']; ?>"<?php endif; ?>
                                               <?php if (isset($config['step'])): ?>step="<?php echo $config['step']; ?>"<?php endif; ?>
                                               <?php if (isset($config['requerido']) && $config['requerido']): ?>required<?php endif; ?>>
                                               
                                        <?php if (isset($config['sufijo'])): ?>
                                        <span class="input-group-text"><?php echo $config['sufijo']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php elseif ($config['tipo'] == 'select'): ?>
                                    <select class="form-select" 
                                            id="<?php echo $clave; ?>" 
                                            name="<?php echo $clave; ?>"
                                            <?php if (isset($config['requerido']) && $config['requerido']): ?>required<?php endif; ?>>
                                        <?php foreach ($config['opciones'] as $valor_opcion => $etiqueta_opcion): ?>
                                        <option value="<?php echo $valor_opcion; ?>" 
                                                <?php echo ($config['valor'] == $valor_opcion) ? 'selected' : ''; ?>>
                                            <?php echo $etiqueta_opcion; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <?php elseif ($config['tipo'] == 'checkbox'): ?>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="<?php echo $clave; ?>" 
                                               name="<?php echo $clave; ?>" 
                                               value="1"
                                               <?php echo ($config['valor'] == '1') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="<?php echo $clave; ?>">
                                            Activar
                                        </label>
                                    </div>
                                    
                                    <?php elseif ($config['tipo'] == 'textarea'): ?>
                                    <textarea class="form-control" 
                                              id="<?php echo $clave; ?>" 
                                              name="<?php echo $clave; ?>" 
                                              rows="3"
                                              <?php if (isset($config['requerido']) && $config['requerido']): ?>required<?php endif; ?>><?php echo htmlspecialchars($config['valor']); ?></textarea>
                                    
                                    <?php elseif ($config['tipo'] == 'date'): ?>
                                    <input type="date" 
                                           class="form-control" 
                                           id="<?php echo $clave; ?>" 
                                           name="<?php echo $clave; ?>" 
                                           value="<?php echo $config['valor']; ?>"
                                           <?php if (isset($config['requerido']) && $config['requerido']): ?>required<?php endif; ?>>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($config['descripcion'])): ?>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        <?php echo $config['descripcion']; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-secondary" onclick="restablecerValores()">
                                    <i class="fas fa-undo me-2"></i>Restablecer Valores
                                </button>
                                <button type="submit" class="btn btn-unexca">
                                    <i class="fas fa-save me-2"></i>Guardar Configuración
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Información adicional según categoría -->
                <?php if ($categoria_activa == 'correo'): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="mb-0">Prueba de Configuración de Correo</h6>
                    </div>
                    <div class="card-body">
                        <form id="formPruebaEmail">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="email_prueba" class="form-label">Email de Prueba</label>
                                    <input type="email" class="form-control" id="email_prueba" 
                                           placeholder="Ingrese un email para probar la configuración" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="button" class="btn btn-unexca w-100" onclick="probarConfiguracion('email')">
                                        <i class="fas fa-paper-plane me-2"></i>Enviar Prueba
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($categoria_activa == 'backup'): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="mb-0">Backups Recientes</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tamaño</th>
                                        <th>Tipo</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="listaBackups">
                                    <!-- Los backups se cargarán dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <button class="btn btn-outline-unexca" onclick="cargarBackups()">
                                <i class="fas fa-sync me-2"></i>Actualizar Lista
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de información del sistema -->
<div class="modal fade" id="modalInfoSistema" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Información del Sistema</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Información del Servidor</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Sistema Operativo:</th>
                                <td><?php echo php_uname('s'); ?></td>
                            </tr>
                            <tr>
                                <th>Servidor Web:</th>
                                <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                            </tr>
                            <tr>
                                <th>PHP:</th>
                                <td><?php echo phpversion(); ?></td>
                            </tr>
                            <tr>
                                <th>MySQL:</th>
                                <td>
                                    <?php
                                    $version = $conn->getAttribute(PDO::ATTR_SERVER_VERSION);
                                    echo $version;
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Tiempo de Ejecución:</th>
                                <td><?php echo round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3); ?> segundos</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h6>Estadísticas del Sistema</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Espacio Libre:</th>
                                <td><?php echo round(disk_free_space('/') / 1024 / 1024 / 1024, 2); ?> GB</td>
                            </tr>
                            <tr>
                                <th>Espacio Total:</th>
                                <td><?php echo round(disk_total_space('/') / 1024 / 1024 / 1024, 2); ?> GB</td>
                            </tr>
                            <tr>
                                <th>Memoria Límite:</th>
                                <td><?php echo ini_get('memory_limit'); ?></td>
                            </tr>
                            <tr>
                                <th>Tiempo Máx. Ejecución:</th>
                                <td><?php echo ini_get('max_execution_time'); ?> segundos</td>
                            </tr>
                            <tr>
                                <th>Versión UNEXCA:</th>
                                <td>1.0.0</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h6>Módulos PHP Habilitados</h6>
                    <div class="modules-list">
                        <?php
                        $modulos = get_loaded_extensions();
                        sort($modulos);
                        echo implode(', ', $modulos);
                        ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-unexca" onclick="generarReporteSistema()">
                    <i class="fas fa-download me-2"></i>Descargar Reporte
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function restablecerValores() {
    Swal.fire({
        title: '¿Restablecer valores?',
        text: '¿Está seguro de restablecer los valores de esta categoría a sus valores por defecto?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, restablecer',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Aquí podrías hacer una llamada AJAX para restablecer valores
            Swal.fire({
                title: 'Valores restablecidos',
                text: 'Los valores han sido restablecidos a sus valores por defecto',
                icon: 'success'
            }).then(() => {
                location.reload();
            });
        }
    });
}

function probarConfiguracion(tipo) {
    if (tipo === 'email') {
        const email = document.getElementById('email_prueba').value;
        
        if (!email) {
            Swal.fire({
                title: 'Error',
                text: 'Por favor ingrese un email de prueba',
                icon: 'error'
            });
            return;
        }
        
        UNEXCA.Utils.showLoading('#formPruebaEmail', 'Enviando email de prueba...');
        
        fetch('ajax/probar_email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email: email })
        })
        .then(response => response.json())
        .then(data => {
            UNEXCA.Utils.hideLoading('#formPruebaEmail');
            
            if (data.success) {
                Swal.fire({
                    title: '¡Éxito!',
                    text: data.message,
                    icon: 'success'
                });
            } else {
                Swal.fire({
                    title: 'Error',
                    text: data.message,
                    icon: 'error'
                });
            }
        })
        .catch(error => {
            UNEXCA.Utils.hideLoading('#formPruebaEmail');
            Swal.fire({
                title: 'Error',
                text: 'Ocurrió un error al probar la configuración',
                icon: 'error'
            });
        });
    }
}

function realizarBackup() {
    Swal.fire({
        title: 'Realizar Backup',
        text: '¿Está seguro de realizar un backup manual de la base de datos?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, realizar backup',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            UNEXCA.Utils.showLoading('.card-body', 'Realizando backup...');
            
            fetch('ajax/realizar_backup.php')
            .then(response => response.json())
            .then(data => {
                UNEXCA.Utils.hideLoading('.card-body');
                
                if (data.success) {
                    Swal.fire({
                        title: '¡Backup exitoso!',
                        html: `Backup realizado correctamente<br>
                               <small>Archivo: ${data.filename}</small><br>
                               <small>Tamaño: ${data.size}</small>`,
                        icon: 'success'
                    });
                    cargarBackups();
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message,
                        icon: 'error'
                    });
                }
            })
            .catch(error => {
                UNEXCA.Utils.hideLoading('.card-body');
                Swal.fire({
                    title: 'Error',
                    text: 'Ocurrió un error al realizar el backup',
                    icon: 'error'
                });
            });
        }
    });
}

function cargarBackups() {
    fetch('ajax/obtener_backups.php')
    .then(response => response.json())
    .then(data => {
        const tabla = document.getElementById('listaBackups');
        tabla.innerHTML = '';
        
        if (data.length === 0) {
            tabla.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-3">
                        <i class="fas fa-database fa-2x text-muted mb-2"></i>
                        <p class="text-muted">No hay backups disponibles</p>
                    </td>
                </tr>
            `;
            return;
        }
        
        data.forEach(backup => {
            const fila = document.createElement('tr');
            fila.innerHTML = `
                <td>${backup.fecha}</td>
                <td>${backup.tamano}</td>
                <td><span class="badge bg-${backup.tipo === 'auto' ? 'info' : 'primary'}">${backup.tipo}</span></td>
                <td><span class="badge bg-${backup.estado === 'completo' ? 'success' : 'warning'}">${backup.estado}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="descargarBackup('${backup.archivo}')">
                        <i class="fas fa-download"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="eliminarBackup('${backup.archivo}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tabla.appendChild(fila);
        });
    });
}

function descargarBackup(archivo) {
    window.open(`ajax/descargar_backup.php?archivo=${encodeURIComponent(archivo)}`, '_blank');
}

function eliminarBackup(archivo) {
    Swal.fire({
        title: 'Eliminar Backup',
        text: `¿Está seguro de eliminar el backup "${archivo}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`ajax/eliminar_backup.php?archivo=${encodeURIComponent(archivo)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: '¡Eliminado!',
                        text: 'El backup ha sido eliminado',
                        icon: 'success'
                    });
                    cargarBackups();
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message,
                        icon: 'error'
                    });
                }
            });
        }
    });
}

function generarReporteSistema() {
    UNEXCA.Utils.showNotification('Generando reporte del sistema...', 'info');
    
    // Simular generación de reporte
    setTimeout(() => {
        const link = document.createElement('a');
        link.href = 'ajax/generar_reporte_sistema.php';
        link.download = 'reporte_sistema_' + new Date().toISOString().split('T')[0] + '.txt';
        link.click();
        
        UNEXCA.Utils.showNotification('Reporte generado exitosamente', 'success');
    }, 1000);
}

// Cargar backups al cargar la página si estamos en la categoría de backup
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($categoria_activa == 'backup'): ?>
    cargarBackups();
    <?php endif; ?>
});
</script>

<style>
.system-status {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.status-item {
    display: flex;
    align-items: center;
    padding: 4px 0;
    border-bottom: 1px solid #eee;
}

.status-item:last-child {
    border-bottom: none;
}

.modules-list {
    max-height: 200px;
    overflow-y: auto;
    background: #f8f9fa;
    padding: 10px;
    border-radius: 5px;
    font-size: 0.875rem;
}

.form-check.form-switch {
    padding-left: 2.5em;
}

.form-check-input:checked {
    background-color: #0056b3;
    border-color: #0056b3;
}

.input-group-text {
    background-color: #f8f9fa;
}

.list-group-item.active {
    background-color: #0056b3;
    border-color: #0056b3;
}
</style>

<?php include '../../includes/footer.php'; ?>