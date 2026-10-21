<?php
session_start();
require 'db.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$is_global = in_array($_SESSION['rol'], ['admin', 'jefe_general']);

// Solo Jefe General (y Admin) pueden ver y gestionar movimientos de entrada y salida
if (!$is_global) {
    header("Location: admin.php");
    exit();
}

// --------- PROCESAR NUEVO MOVIMIENTO (ENTRADA / SALIDA) ---------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'nuevo') {
    $id_activo = (int)$_POST['id_activo'];
    $tipo_movimiento = ($_POST['tipo_movimiento'] === 'Entrada') ? 'Entrada' : 'Salida';
    $id_evento = !empty($_POST['id_evento']) ? (int)$_POST['id_evento'] : "NULL";
    $fecha_movimiento = !empty($_POST['fecha_movimiento']) ? $conn->real_escape_string(str_replace('T', ' ', $_POST['fecha_movimiento'])) : date('Y-m-d H:i:s');
    $observaciones = $conn->real_escape_string($_POST['observaciones']);
    $usuario_id = (int)$_SESSION['id_usuario'];
    $estado_retorno = isset($_POST['estado_retorno']) ? $conn->real_escape_string($_POST['estado_retorno']) : 'Disponible';

    $sql_insert = "INSERT INTO movimientos_equipos (id_activo, id_evento, usuario_id, tipo_movimiento, fecha_movimiento, observaciones) 
                   VALUES ($id_activo, $id_evento, $usuario_id, '$tipo_movimiento', '$fecha_movimiento', '$observaciones')";

    if ($conn->query($sql_insert)) {
        // Actualizar automáticamente el estado del activo según el movimiento
        if ($tipo_movimiento === 'Salida') {
            $nuevo_estado = 'En uso';
        } else {
            $nuevo_estado = in_array($estado_retorno, ['Disponible', 'En mantenimiento', 'Dañado']) ? $estado_retorno : 'Disponible';
        }
        $conn->query("UPDATE activos SET estado = '$nuevo_estado' WHERE id = $id_activo");

        // Registrar también en auditoria_logs del modelo RBAC
        $accion_log = "Registro de $tipo_movimiento de equipo ID #$id_activo";
        $conn->query("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada) VALUES ($usuario_id, '$accion_log', 'movimientos_equipos')");

        $_SESSION['mensaje'] = "Movimiento de '$tipo_movimiento' registrado y estado del equipo actualizado a '$nuevo_estado'.";
    } else {
        $_SESSION['error'] = "Error al registrar el movimiento: " . $conn->error;
    }
    header("Location: movimientos.php");
    exit();
}

// --------- PROCESAR ELIMINAR MOVIMIENTO ---------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'eliminar') {
    $id = (int)$_POST['id_movimiento'];
    if ($conn->query("DELETE FROM movimientos_equipos WHERE id = $id")) {
        $_SESSION['mensaje'] = "Registro de movimiento eliminado.";
    } else {
        $_SESSION['error'] = "Error al eliminar el movimiento: " . $conn->error;
    }
    header("Location: movimientos.php");
    exit();
}
// ----------------------------------------------------------------

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filtro_tipo = isset($_GET['tipo']) ? $conn->real_escape_string($_GET['tipo']) : '';

// Consulta usando JOIN (cumpliendo 1FN, 2FN y 3FN sin redundancia de datos)
$sql = "SELECT m.*, 
               a.codigo as activo_codigo, a.nombre as activo_nombre, a.estado as activo_estado,
               d.nombre as departamento_nombre,
               e.titulo as evento_titulo,
               c.nombre as cliente_nombre,
               u.nombre as responsable_nombre
        FROM movimientos_equipos m
        INNER JOIN activos a ON m.id_activo = a.id
        LEFT JOIN departamentos d ON a.id_departamento = d.id
        LEFT JOIN eventos e ON m.id_evento = e.id
        LEFT JOIN clientes c ON e.id_cliente = c.id
        INNER JOIN usuario u ON m.usuario_id = u.id";

$condiciones = [];
if ($search != '') {
    $condiciones[] = "(a.codigo LIKE '%$search%' OR a.nombre LIKE '%$search%' OR e.titulo LIKE '%$search%' OR m.observaciones LIKE '%$search%')";
}
if ($filtro_tipo == 'Salida' || $filtro_tipo == 'Entrada') {
    $condiciones[] = "m.tipo_movimiento = '$filtro_tipo'";
}
if (count($condiciones) > 0) {
    $sql .= " WHERE " . implode(" AND ", $condiciones);
}
$sql .= " ORDER BY m.fecha_movimiento DESC, m.id DESC";
$result = $conn->query($sql);

$activos_list = $conn->query("SELECT a.id, a.codigo, a.nombre, a.estado, d.nombre as dep_nombre FROM activos a LEFT JOIN departamentos d ON a.id_departamento = d.id ORDER BY a.nombre ASC");
$eventos_list = $conn->query("SELECT e.id, e.titulo, e.fecha, c.nombre as cliente_nombre FROM eventos e LEFT JOIN clientes c ON e.id_cliente = c.id ORDER BY e.fecha DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos de Equipos - DN Producciones</title>
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
                    <?php if($is_global): ?>
                    <li class="nav-item"><a class="nav-link" href="clientes.php">Clientes</a></li>
                    <li class="nav-item"><a class="nav-link active" href="movimientos.php">Movimientos</a></li>
                    <?php endif; ?>
                    <?php if(isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?><li class="nav-item"><a class="nav-link" href="usuarios.php">Usuarios</a></li><?php endif; ?>
                </ul>
                <span class="navbar-text me-3 text-white">
                    <strong class="badge bg-primary"><?php echo $is_global ? 'Jefe General' : 'Jefe Dept'; ?></strong> | Hola, <?php echo htmlspecialchars($_SESSION['nombre']); ?>
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

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">Movimientos de Entrada y Salida de Equipos</h2>
                <small class="text-muted">Al registrar una Salida o Entrada se actualiza automáticamente el estado del equipo en Activos.</small>
            </div>
            <button class="btn btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoMovimiento">
                <i class="bi bi-arrow-left-right"></i> Registrar Entrada / Salida
            </button>
        </div>
        
        <form method="GET" action="movimientos.php" class="row g-2 mb-4">
            <div class="col-md-3">
                <select name="tipo" class="form-select shadow-sm" onchange="this.form.submit()">
                    <option value="">Todos los movimientos</option>
                    <option value="Salida" <?php echo ($filtro_tipo == 'Salida') ? 'selected' : ''; ?>>Salidas (Hacia Eventos)</option>
                    <option value="Entrada" <?php echo ($filtro_tipo == 'Entrada') ? 'selected' : ''; ?>>Entradas (Retorno a Almacén)</option>
                </select>
            </div>
            <div class="col-md-9">
                <div class="input-group shadow-sm">
                    <input type="text" name="search" class="form-control" placeholder="Buscar por código de equipo, nombre, evento u observación..." value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Buscar</button>
                    <?php if($search != '' || $filtro_tipo != ''): ?>
                        <a href="movimientos.php" class="btn btn-secondary">Limpiar</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle bg-white">
                <thead class="table-dark">
                    <tr>
                        <th>Fecha y Hora</th>
                        <th>Tipo</th>
                        <th>Equipo (Activo)</th>
                        <th>Evento / Destino</th>
                        <th>Observaciones</th>
                        <th>Autorizado por</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result && $result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            $fecha_fmt = date('d/m/Y h:i A', strtotime($row['fecha_movimiento']));
                            $is_salida = ($row['tipo_movimiento'] === 'Salida');
                            $badge_tipo = $is_salida 
                                ? "<span class='badge bg-warning text-dark'><i class='bi bi-box-arrow-right'></i> Salida</span>" 
                                : "<span class='badge bg-success'><i class='bi bi-box-arrow-in-left'></i> Entrada</span>";
                            
                            $evento_str = !empty($row['evento_titulo']) 
                                ? "<strong>" . htmlspecialchars($row['evento_titulo']) . "</strong>" . (!empty($row['cliente_nombre']) ? "<br><small class='text-muted'>Cliente: " . htmlspecialchars($row['cliente_nombre']) . "</small>" : "")
                                : "<span class='text-muted'><i>Almacén / General</i></span>";

                            echo "<tr>
                                <td><strong>{$fecha_fmt}</strong></td>
                                <td>{$badge_tipo}</td>
                                <td>
                                    <strong>" . htmlspecialchars($row['activo_codigo']) . " - " . htmlspecialchars($row['activo_nombre']) . "</strong><br>
                                    <small class='text-muted'>Depto: " . htmlspecialchars($row['departamento_nombre']) . " | Estado actual: " . htmlspecialchars($row['activo_estado']) . "</small>
                                </td>
                                <td>{$evento_str}</td>
                                <td>" . htmlspecialchars($row['observaciones'] ?: 'Sin observaciones') . "</td>
                                <td><span class='badge bg-secondary'>" . htmlspecialchars($row['responsable_nombre']) . "</span></td>
                                <td class='text-center'>
                                    <button class='btn btn-danger btn-sm' onclick='confirmarEliminarMovimiento({$row['id']})' title='Eliminar registro'>
                                        <i class='bi bi-trash'></i>
                                    </button>
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' class='text-center py-4 text-muted'>No hay movimientos de entrada o salida registrados aún.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Formulario Oculto para Eliminar Movimiento -->
    <form id="formEliminarMovimiento" method="POST" action="movimientos.php" style="display:none;">
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="id_movimiento" id="inputEliminarMovimientoId">
    </form>

    <!-- Modal NUEVO MOVIMIENTO -->
    <div class="modal fade" id="modalNuevoMovimiento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="bi bi-arrow-left-right"></i> Registrar Entrada / Salida de Equipo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="movimientos.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="nuevo">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Tipo de Movimiento</label>
                                <select name="tipo_movimiento" id="select_tipo_movimiento" class="form-select" required onchange="toggleEstadoRetorno()">
                                    <option value="Salida">Salida (Equipo sale a evento / pasa a "En uso")</option>
                                    <option value="Entrada">Entrada (Equipo regresa a almacén)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Fecha y Hora</label>
                                <input type="datetime-local" name="fecha_movimiento" class="form-control" required value="<?php echo date('Y-m-d\TH:i'); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Seleccionar Equipo (Activo)</label>
                            <select name="id_activo" class="form-select" required>
                                <option value="">Seleccione el equipo...</option>
                                <?php 
                                if ($activos_list && $activos_list->num_rows > 0) {
                                    $activos_list->data_seek(0);
                                    while($ac = $activos_list->fetch_assoc()): ?>
                                        <option value="<?php echo $ac['id']; ?>">
                                            <?php echo htmlspecialchars("[{$ac['codigo']}] {$ac['nombre']} ({$ac['dep_nombre']}) - Estado actual: {$ac['estado']}"); ?>
                                        </option>
                                    <?php endwhile;
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-3" id="div_estado_retorno" style="display: none;">
                            <label class="form-label fw-bold text-success">Estado en el que regresa el equipo al almacén</label>
                            <select name="estado_retorno" class="form-select">
                                <option value="Disponible">Disponible (En perfecto estado)</option>
                                <option value="En mantenimiento">En mantenimiento (Requiere revisión)</option>
                                <option value="Dañado">Dañado (Regresó con avería)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Evento Relacionado (Opcional)</label>
                            <select name="id_evento" class="form-select">
                                <option value="">Ninguno / Movimiento general de almacén</option>
                                <?php 
                                if ($eventos_list && $eventos_list->num_rows > 0) {
                                    $eventos_list->data_seek(0);
                                    while($ev = $eventos_list->fetch_assoc()): 
                                        $cl_info = !empty($ev['cliente_nombre']) ? " - Cliente: " . $ev['cliente_nombre'] : "";
                                    ?>
                                        <option value="<?php echo $ev['id']; ?>">
                                            <?php echo htmlspecialchars($ev['titulo'] . " (" . date('d/m/Y', strtotime($ev['fecha'])) . ")" . $cl_info); ?>
                                        </option>
                                    <?php endwhile;
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Observaciones / Detalles del Movimiento</label>
                            <textarea name="observaciones" class="form-control" rows="2" placeholder="Ej: Salió con todos sus cables de poder / Regresó sin novedades..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Guardar Movimiento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleEstadoRetorno() {
            const tipo = document.getElementById('select_tipo_movimiento').value;
            const divRetorno = document.getElementById('div_estado_retorno');
            divRetorno.style.display = (tipo === 'Entrada') ? 'block' : 'none';
        }

        function confirmarEliminarMovimiento(id) {
            if(confirm('¿Estás SEGURO de que deseas eliminar este registro del historial de movimientos?')) {
                document.getElementById('inputEliminarMovimientoId').value = id;
                document.getElementById('formEliminarMovimiento').submit();
            }
        }
    </script>
</body>
</html>
