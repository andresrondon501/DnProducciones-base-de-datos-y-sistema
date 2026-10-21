<?php
session_start();
require 'db.php';

// Solo administradores (admin) pueden gestionar usuarios. Jefe General NO puede.
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: admin.php");
    exit();
}

// --------- PROCESAR FORMULARIO DE NUEVO USUARIO ---------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'nuevo') {
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $email = $conn->real_escape_string($_POST['email']);
    $password_raw = $_POST['password'];
    $rol_name = $conn->real_escape_string($_POST['rol']);
    $id_departamento = !empty($_POST['id_departamento']) ? (int)$_POST['id_departamento'] : "NULL";
    
    // Buscar ID del rol
    $res_rol = $conn->query("SELECT id FROM rol WHERE nombre_rol = '$rol_name'");
    $id_rol = ($res_rol->num_rows > 0) ? $res_rol->fetch_assoc()['id'] : 2; // Default a jefe_departamento
    
    $check = $conn->query("SELECT id FROM usuario WHERE email = '$email'");
    if ($check->num_rows > 0) {
        $_SESSION['error'] = "El correo '$email' ya está registrado.";
    } else {
        $password_plain = $conn->real_escape_string($password_raw);
        $sql_insert = "INSERT INTO usuario (nombre, email, password_hash, activo, id_departamento) 
                       VALUES ('$nombre', '$email', '$password_plain', 1, $id_departamento)";
        if ($conn->query($sql_insert)) {
            $new_user_id = $conn->insert_id;
            $conn->query("INSERT INTO r_usuario_rol (usuario_id, rol_id) VALUES ($new_user_id, $id_rol)");
            $_SESSION['mensaje'] = "Usuario '$nombre' registrado correctamente.";
        } else {
            $_SESSION['error'] = "Error al guardar el usuario: " . $conn->error;
        }
    }
    header("Location: usuarios.php");
    exit();
}

// --------- PROCESAR FORMULARIO DE EDITAR USUARIO ---------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'editar') {
    $id = (int)$_POST['id_usuario'];
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $rol_name = $conn->real_escape_string($_POST['rol']);
    $id_departamento = !empty($_POST['id_departamento']) ? (int)$_POST['id_departamento'] : "NULL";
    
    // Buscar ID del rol
    $res_rol = $conn->query("SELECT id FROM rol WHERE nombre_rol = '$rol_name'");
    $id_rol = ($res_rol->num_rows > 0) ? $res_rol->fetch_assoc()['id'] : 2;
    
    $check = $conn->query("SELECT id FROM usuario WHERE email = '$email' AND id != $id");
    if ($check->num_rows > 0) {
        $_SESSION['error'] = "El correo '$email' ya pertenece a otro usuario.";
    } else {
        $password = $conn->real_escape_string($password);
        
        $sql_update = "UPDATE usuario SET nombre='$nombre', email='$email', password_hash='$password', id_departamento=$id_departamento WHERE id=$id";
        if ($conn->query($sql_update)) {
            // Actualizar rol
            $check_rol = $conn->query("SELECT id FROM r_usuario_rol WHERE usuario_id = $id");
            if ($check_rol->num_rows > 0) {
                $conn->query("UPDATE r_usuario_rol SET rol_id=$id_rol WHERE usuario_id=$id");
            } else {
                $conn->query("INSERT INTO r_usuario_rol (usuario_id, rol_id) VALUES ($id, $id_rol)");
            }
            
            $_SESSION['mensaje'] = "Usuario actualizado correctamente.";
            if ($id == $_SESSION['id_usuario']) {
                $_SESSION['nombre'] = $nombre;
                $_SESSION['rol'] = $rol_name;
            }
        } else {
            $_SESSION['error'] = "Error al actualizar el usuario: " . $conn->error;
        }
    }
    header("Location: usuarios.php");
    exit();
}

// --------- PROCESAR ELIMINAR USUARIO ---------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'eliminar') {
    $id = (int)$_POST['id_usuario'];
    
    if ($id == $_SESSION['id_usuario']) {
        $_SESSION['error'] = "No puedes eliminar tu propia cuenta mientras estás en sesión.";
    } else {
        // Al eliminar usuario, eliminamos sus roles (o el ON DELETE CASCADE de la BD lo hará)
        $conn->query("DELETE FROM r_usuario_rol WHERE usuario_id = $id");
        if ($conn->query("DELETE FROM usuario WHERE id = $id")) {
            $_SESSION['mensaje'] = "Usuario eliminado permanentemente.";
        } else {
            $_SESSION['error'] = "Error al eliminar el usuario: " . $conn->error;
        }
    }
    header("Location: usuarios.php");
    exit();
}
// --------------------------------------------------------

$departamentos = $conn->query("SELECT * FROM departamentos");
$roles = $conn->query("SELECT * FROM rol ORDER BY id ASC");

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

$sql = "SELECT u.*, d.nombre as departamento_nombre, r.nombre_rol as rol_name 
        FROM usuario u 
        LEFT JOIN departamentos d ON u.id_departamento = d.id 
        LEFT JOIN r_usuario_rol ur ON u.id = ur.usuario_id 
        LEFT JOIN rol r ON ur.rol_id = r.id";

if ($search != '') {
    $sql .= " WHERE u.nombre LIKE '%$search%' OR u.email LIKE '%$search%'";
}

$sql .= " ORDER BY u.id DESC";
$result = $conn->query($sql);

// --------- CONSULTAS DE AUDITORÍA DE VISITAS (SEMANA / MES) SOLO PARA ADMIN ---------
$periodo_auditoria = isset($_GET['periodo']) ? $_GET['periodo'] : 'semana';
$vista_activa = isset($_GET['vista']) ? $_GET['vista'] : (isset($_GET['periodo']) ? 'auditoria' : 'usuarios');

if ($periodo_auditoria === 'mes') {
    $condicion_fecha = "a.fecha >= (NOW() - INTERVAL 30 DAY)";
    $titulo_periodo = "Este Mes (Últimos 30 días)";
} elseif ($periodo_auditoria === 'todo') {
    $condicion_fecha = "1=1";
    $titulo_periodo = "Todo el Historial";
} else {
    $periodo_auditoria = 'semana';
    $condicion_fecha = "a.fecha >= (NOW() - INTERVAL 7 DAY)";
    $titulo_periodo = "Esta Semana (Últimos 7 días)";
}

// Contadores rápidos de visitas (Semana y Mes)
$visitas_semana = $conn->query("SELECT COUNT(*) as total FROM auditoria_logs WHERE tabla_afectada = 'usuario' AND fecha >= (NOW() - INTERVAL 7 DAY)")->fetch_assoc()['total'] ?? 0;
$visitas_mes = $conn->query("SELECT COUNT(*) as total FROM auditoria_logs WHERE tabla_afectada = 'usuario' AND fecha >= (NOW() - INTERVAL 30 DAY)")->fetch_assoc()['total'] ?? 0;

// Resumen agrupado por persona (quiénes han entrado y cuántas veces en el período)
$sql_resumen_visitas = "SELECT u.nombre, u.email, r.nombre_rol as rol_name, d.nombre as departamento_nombre,
                               COUNT(a.id) as total_visitas,
                               MAX(a.fecha) as ultima_visita
                        FROM auditoria_logs a
                        INNER JOIN usuario u ON a.usuario_id = u.id
                        LEFT JOIN r_usuario_rol ur ON u.id = ur.usuario_id
                        LEFT JOIN rol r ON ur.rol_id = r.id
                        LEFT JOIN departamentos d ON u.id_departamento = d.id
                        WHERE a.tabla_afectada = 'usuario' AND $condicion_fecha
                        GROUP BY u.id
                        ORDER BY ultima_visita DESC";
$res_resumen_visitas = $conn->query($sql_resumen_visitas);

// Detalle cronológico de todas las visitas en el período
$sql_detalle_visitas = "SELECT a.*, u.nombre, u.email, r.nombre_rol as rol_name, d.nombre as departamento_nombre
                        FROM auditoria_logs a
                        INNER JOIN usuario u ON a.usuario_id = u.id
                        LEFT JOIN r_usuario_rol ur ON u.id = ur.usuario_id
                        LEFT JOIN rol r ON ur.rol_id = r.id
                        LEFT JOIN departamentos d ON u.id_departamento = d.id
                        WHERE $condicion_fecha
                        ORDER BY a.fecha DESC";
$res_detalle_visitas = $conn->query($sql_detalle_visitas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - DN Producciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-image: url('fondo.jpg'); background-size: cover; background-position: center; background-attachment: fixed; }
        .dashboard-container { background: rgba(255, 255, 255, 0.95); border-radius: 10px; padding: 30px; margin-top: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.5); min-height: 80vh; }
    </style>
    <link rel="icon" href="logo_app.png" type="image/png">
</head>
<body>
<!-- Loader Overlay -->
<div id="page-loader" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #212529; z-index: 9999; display: flex; flex-direction: column; justify-content: center; align-items: center; transition: opacity 0.4s ease; color: white;">
    <div class="spinner-border text-primary" style="width: 4rem; height: 4rem; border-width: 0.4em;" role="status">
        <span class="visually-hidden">Cargando...</span>
    </div>
    <h4 class="mt-4 fw-bold" style="letter-spacing: 2px;">DN PRODUCCIONES</h4>
    <p id="loader-text" class="text-muted mt-2" style="font-size: 1.1em;">Cargando datos...</p>
</div>

<script>
    var hasSuccessMessage = <?php echo isset($_SESSION['mensaje']) ? 'true' : 'false'; ?>;
    
    window.addEventListener('load', function() {
        var loader = document.getElementById('page-loader');
        var loaderText = document.getElementById('loader-text');
        
        if(loader) {
            // Spinner por 1 segundo
            setTimeout(function() {
                if (loaderText) {
                    if (hasSuccessMessage) {
                        loaderText.innerText = "Cargado con éxito";
                        loaderText.className = "text-success fw-bold mt-2";
                    } else {
                        loaderText.innerText = "Finalizado";
                        loaderText.className = "text-info fw-bold mt-2";
                    }
                }
                
                // Mensaje de finalizado por 0.8 segundos (Total 1.8s)
                setTimeout(function() {
                    loader.style.opacity = '0';
                    setTimeout(function() {
                        loader.style.display = 'none';
                    }, 400); 
                }, 800);
            }, 1000);
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        var links = document.querySelectorAll('a.nav-link, a.navbar-brand, a.btn');
        links.forEach(function(link) {
            link.addEventListener('click', function(e) {
                if (link.getAttribute('href') !== '#' && !link.hasAttribute('data-bs-toggle') && !link.hasAttribute('onclick')) {
                    showLoader("Cargando datos...");
                }
            });
        });

        var forms = document.querySelectorAll('form');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                showLoader("Cargando archivo...");
            });
        });
        
        function showLoader(text) {
            var loader = document.getElementById('page-loader');
            var loaderText = document.getElementById('loader-text');
            if(loader) {
                if (loaderText && text) {
                    loaderText.innerText = text;
                    loaderText.className = "text-muted mt-2";
                }
                loader.style.display = 'flex';
                setTimeout(function() {
                    loader.style.opacity = '1';
                }, 10);
            }
        }
    });
</script>



    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="admin.php">
                <img src="logo_dn_transparente.png" alt="Logo" width="70" height="70" class="me-2" style="object-fit: contain;">
                DN Producciones
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="admin.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="activos.php">Activos</a></li>
                    <li class="nav-item"><a class="nav-link" href="inventario.php">Inventario</a></li>
                    <li class="nav-item"><a class="nav-link" href="eventos.php">Eventos</a></li>
                    <li class="nav-item"><a class="nav-link" href="clientes.php">Clientes</a></li>
                    <li class="nav-item"><a class="nav-link" href="movimientos.php">Movimientos</a></li>
                    <?php if(isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?><li class="nav-item"><a class="nav-link active" href="usuarios.php">Usuarios</a></li><?php endif; ?>
                </ul>
                <span class="navbar-text me-3 text-white">
                    <strong class="badge bg-primary">Jefe General</strong> | Hola, <?php echo htmlspecialchars($_SESSION['nombre']); ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="container dashboard-container">
        
        <?php
        if (isset($_SESSION['mensaje'])) {
            echo "<div class='alert alert-success alert-dismissible fade show'><i class='bi bi-check-circle-fill'></i> {$_SESSION['mensaje']}<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            unset($_SESSION['mensaje']);
        }
        if (isset($_SESSION['error'])) {
            echo "<div class='alert alert-danger alert-dismissible fade show'><i class='bi bi-exclamation-triangle-fill'></i> {$_SESSION['error']}<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            unset($_SESSION['error']);
        }
        ?>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2>Gestión y Auditoría de Usuarios</h2>
            <div class="d-flex gap-2">
                <a href="usuarios.php?vista=usuarios" class="btn <?php echo ($vista_activa !== 'auditoria') ? 'btn-dark' : 'btn-outline-dark'; ?> fw-bold shadow-sm">
                    <i class="bi bi-people-fill"></i> Lista de Usuarios
                </a>
                <a href="usuarios.php?vista=auditoria&periodo=semana" class="btn <?php echo ($vista_activa === 'auditoria') ? 'btn-primary' : 'btn-outline-primary'; ?> fw-bold shadow-sm">
                    <i class="bi bi-shield-check"></i> Auditoría de Visitas (Semana / Mes)
                </a>
                <?php if ($vista_activa !== 'auditoria'): ?>
                <button class="btn btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoUsuario">
                    <i class="bi bi-person-plus-fill"></i> Registrar Nuevo
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($vista_activa === 'auditoria'): ?>
        <!-- SECCIÓN DE AUDITORÍA DE VISITAS A LA APLICACIÓN (SOLO ADMIN) -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
                <div>
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i> Control de Visitas e Ingresos a la Aplicación</h5>
                    <small class="text-white-50">Mostrando registros de: <strong><?php echo $titulo_periodo; ?></strong></small>
                </div>
                <div class="btn-group" role="group">
                    <a href="usuarios.php?vista=auditoria&periodo=semana" class="btn btn-sm <?php echo ($periodo_auditoria === 'semana') ? 'btn-info text-white fw-bold' : 'btn-outline-light'; ?>">
                        <i class="bi bi-calendar-week"></i> Esta Semana (7 días)
                    </a>
                    <a href="usuarios.php?vista=auditoria&periodo=mes" class="btn btn-sm <?php echo ($periodo_auditoria === 'mes') ? 'btn-info text-white fw-bold' : 'btn-outline-light'; ?>">
                        <i class="bi bi-calendar-month"></i> Este Mes (30 días)
                    </a>
                    <a href="usuarios.php?vista=auditoria&periodo=todo" class="btn btn-sm <?php echo ($periodo_auditoria === 'todo') ? 'btn-info text-white fw-bold' : 'btn-outline-light'; ?>">
                        <i class="bi bi-list-ul"></i> Todo
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="p-3 rounded bg-primary bg-opacity-10 border border-primary d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-primary fw-bold mb-1">Visitas en la Última Semana</h6>
                                <small class="text-muted">Ingresos registrados en los últimos 7 días</small>
                            </div>
                            <h2 class="text-primary fw-bold mb-0"><?php echo $visitas_semana; ?></h2>
                        </div>
                    </div>
                    <div class="col-md-6 mt-3 mt-md-0">
                        <div class="p-3 rounded bg-success bg-opacity-10 border border-success d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-success fw-bold mb-1">Visitas en el Último Mes</h6>
                                <small class="text-muted">Ingresos registrados en los últimos 30 días</small>
                            </div>
                            <h2 class="text-success fw-bold mb-0"><?php echo $visitas_mes; ?></h2>
                        </div>
                    </div>
                </div>

                <h5 class="fw-bold mb-3"><i class="bi bi-person-check-fill text-primary"></i> Personas que han entrado a la aplicación (<?php echo $titulo_periodo; ?>)</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-hover table-bordered align-middle bg-white">
                        <thead class="table-dark">
                            <tr>
                                <th>Usuario</th>
                                <th>Correo</th>
                                <th>Rol</th>
                                <th>Departamento</th>
                                <th class="text-center">Cantidad de Visitas</th>
                                <th>Última Entrada</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($res_resumen_visitas && $res_resumen_visitas->num_rows > 0) {
                                while($rv = $res_resumen_visitas->fetch_assoc()) {
                                    $rol_n = $rv['rol_name'] ?? 'sin_rol';
                                    $badge_c = ($rol_n == 'admin' || $rol_n == 'jefe_general') ? 'bg-danger' : 'bg-primary';
                                    $rol_s = ucfirst(str_replace('_', ' ', $rol_n));
                                    $dep_s = empty($rv['departamento_nombre']) ? 'Global / N/A' : $rv['departamento_nombre'];
                                    $ult_fecha = date('d/m/Y h:i A', strtotime($rv['ultima_visita']));

                                    echo "<tr>
                                        <td><strong>" . htmlspecialchars($rv['nombre']) . "</strong></td>
                                        <td>" . htmlspecialchars($rv['email']) . "</td>
                                        <td><span class='badge {$badge_c}'>{$rol_s}</span></td>
                                        <td>" . htmlspecialchars($dep_s) . "</td>
                                        <td class='text-center'><span class='badge bg-success fs-6'>{$rv['total_visitas']}</span></td>
                                        <td><i class='bi bi-clock text-muted me-1'></i> {$ult_fecha}</td>
                                    </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6' class='text-center py-3 text-muted'>Ningún usuario ha ingresado en este período.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <h5 class="fw-bold mb-3"><i class="bi bi-journal-text text-secondary"></i> Bitácora Detallada de Actividad y Visitas (<?php echo $titulo_periodo; ?>)</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered align-middle bg-white">
                        <thead class="table-secondary">
                            <tr>
                                <th>Fecha y Hora Exacta</th>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th>Acción Registrada</th>
                                <th>Módulo / Tabla</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($res_detalle_visitas && $res_detalle_visitas->num_rows > 0) {
                                while($dv = $res_detalle_visitas->fetch_assoc()) {
                                    $fecha_exacta = date('d/m/Y h:i:s A', strtotime($dv['fecha']));
                                    $rol_s = ucfirst(str_replace('_', ' ', $dv['rol_name'] ?? 'sin_rol'));
                                    echo "<tr>
                                        <td><strong>{$fecha_exacta}</strong></td>
                                        <td>" . htmlspecialchars($dv['nombre']) . " <small class='text-muted'>(" . htmlspecialchars($dv['email']) . ")</small></td>
                                        <td><span class='badge bg-secondary'>{$rol_s}</span></td>
                                        <td>" . htmlspecialchars($dv['accion']) . "</td>
                                        <td><code>" . htmlspecialchars($dv['tabla_afectada']) . "</code></td>
                                    </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5' class='text-center py-3 text-muted'>No hay registros de auditoría en este período.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
        
        <form method="GET" action="usuarios.php" class="mb-4">
            <div class="input-group shadow-sm">
                <input type="text" name="search" class="form-control" placeholder="Buscar por nombre o correo..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Buscar</button>
                <?php if($search != ''): ?>
                    <a href="usuarios.php" class="btn btn-secondary">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle bg-white">
                <thead class="table-dark">
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Contraseña</th>
                        <th>Rol</th>
                        <th>Departamento</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            $rol_name = $row['rol_name'] ?? 'sin_rol';
                            $badge_class = ($rol_name == 'admin' || $rol_name == 'jefe_general') ? 'bg-danger' : 'bg-primary';
                            $rol_str = ucfirst(str_replace('_', ' ', $rol_name));
                            $dep_str = empty($row['departamento_nombre']) ? 'N/A' : $row['departamento_nombre'];

                            echo "<tr>
                                <td><strong>{$row['nombre']}</strong></td>
                                <td>{$row['email']}</td>
                                <td>{$row['password_hash']}</td>
                                <td><span class='badge {$badge_class}'>{$rol_str}</span></td>
                                <td>{$dep_str}</td>
                                <td class='text-center'>
                                    <button class='btn btn-primary btn-sm me-1' onclick='abrirModalEditarUsuario(this)' 
                                        data-id='{$row['id']}'
                                        data-nombre='{$row['nombre']}'
                                        data-email='{$row['email']}'
                                        data-pass='{$row['password_hash']}'
                                        data-rol='{$rol_name}'
                                        data-dep='{$row['id_departamento']}' title='Editar'>
                                        <i class='bi bi-pencil'></i>
                                    </button>
                                    <button class='btn btn-danger btn-sm' onclick='confirmarEliminarUsuario({$row['id']})' title='Eliminar'>
                                        <i class='bi bi-trash'></i>
                                    </button>
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center py-4 text-muted'>No se encontraron usuarios.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Formulario Oculto para Eliminar Usuario -->
    <form id="formEliminarUsuario" method="POST" action="usuarios.php" style="display:none;">
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="id_usuario" id="inputEliminarUsuarioId">
    </form>

    <!-- Modal NUEVO USUARIO -->
    <div class="modal fade" id="modalNuevoUsuario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="bi bi-person-plus"></i> Registrar Nuevo Usuario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="usuarios.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="nuevo">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Nombre Completo</label>
                                <input type="text" name="nombre" class="form-control" required placeholder="Ej: Juan Pérez">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Correo Electrónico</label>
                                <input type="email" name="email" class="form-control" required placeholder="Ej: juan@mail.com">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Contraseña</label>
                                <input type="text" name="password" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Rol en el Sistema</label>
                                <select name="rol" id="nuevo_rol" class="form-select" required onchange="toggleDepartamento('nuevo')">
                                    <option value="">Seleccione un rol...</option>
                                    <?php 
                                    $roles->data_seek(0);
                                    while($r = $roles->fetch_assoc()) {
                                        echo "<option value='{$r['nombre_rol']}'>{$r['descripcion']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3" id="div_nuevo_departamento">
                            <label class="form-label fw-bold">Asignar a Departamento</label>
                            <select name="id_departamento" id="nuevo_id_departamento" class="form-select" required>
                                <option value="">Seleccione un departamento...</option>
                                <?php 
                                $departamentos->data_seek(0);
                                while($dep = $departamentos->fetch_assoc()) echo "<option value='{$dep['id']}'>{$dep['nombre']}</option>";
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Guardar Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal EDITAR USUARIO -->
    <div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Editar Usuario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="usuarios.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id_usuario" id="edit_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Nombre Completo</label>
                                <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Correo Electrónico</label>
                                <input type="email" name="email" id="edit_email" class="form-control" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Contraseña</label>
                                <input type="text" name="password" id="edit_password" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Rol en el Sistema</label>
                                <select name="rol" id="edit_rol" class="form-select" required onchange="toggleDepartamento('edit')">
                                    <option value="">Seleccione un rol...</option>
                                    <?php 
                                    $roles->data_seek(0);
                                    while($r = $roles->fetch_assoc()) {
                                        echo "<option value='{$r['nombre_rol']}'>{$r['descripcion']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3" id="div_edit_departamento">
                            <label class="form-label fw-bold">Asignar a Departamento</label>
                            <select name="id_departamento" id="edit_id_departamento" class="form-select" required>
                                <option value="">Seleccione un departamento...</option>
                                <?php 
                                $departamentos->data_seek(0);
                                while($dep = $departamentos->fetch_assoc()) echo "<option value='{$dep['id']}'>{$dep['nombre']}</option>";
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Actualizar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleDepartamento(prefijo) {
            const rol = document.getElementById(prefijo + '_rol').value;
            const divDep = document.getElementById('div_' + prefijo + '_departamento');
            const selectDep = document.getElementById(prefijo + '_id_departamento');
            
            if (rol === 'admin' || rol === 'jefe_general') {
                divDep.style.display = 'none';
                selectDep.required = false;
                selectDep.value = '';
            } else {
                divDep.style.display = 'block';
                selectDep.required = true;
            }
        }

        function abrirModalEditarUsuario(btn) {
            document.getElementById('edit_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_nombre').value = btn.getAttribute('data-nombre');
            document.getElementById('edit_email').value = btn.getAttribute('data-email');
            document.getElementById('edit_password').value = btn.getAttribute('data-pass');
            
            let rols = btn.getAttribute('data-rol');
            document.getElementById('edit_rol').value = rols;
            
            const depVal = btn.getAttribute('data-dep');
            document.getElementById('edit_id_departamento').value = (depVal === 'null' || !depVal) ? '' : depVal;
            
            toggleDepartamento('edit');
            
            var modalEdit = new bootstrap.Modal(document.getElementById('modalEditarUsuario'));
            modalEdit.show();
        }

        function confirmarEliminarUsuario(id) {
            if(confirm('¿Estás SEGURO de que deseas ELIMINAR permanentemente a este usuario del sistema?')) {
                document.getElementById('inputEliminarUsuarioId').value = id;
                document.getElementById('formEliminarUsuario').submit();
            }
        }
    </script>
</body>
</html>
