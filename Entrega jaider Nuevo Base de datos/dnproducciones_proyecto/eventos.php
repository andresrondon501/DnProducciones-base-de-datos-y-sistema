<?php
session_start();
require 'db.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$is_global = in_array($_SESSION['rol'], ['admin', 'jefe_general']);

// Solo roles globales pueden modificar eventos
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$is_global) {
    $_SESSION['error'] = "No tienes permisos para modificar eventos.";
    header("Location: eventos.php");
    exit();
}

// --------- PROCESAR FORMULARIO DE NUEVO EVENTO ---------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'nuevo') {
    $titulo = $conn->real_escape_string($_POST['titulo']);
    $descripcion = $conn->real_escape_string($_POST['descripcion']);
    $id_cliente = !empty($_POST['id_cliente']) ? (int)$_POST['id_cliente'] : "NULL";
    $fecha = $conn->real_escape_string($_POST['fecha']);
    $hora_inicio = $conn->real_escape_string($_POST['hora_inicio']);
    $hora_fin = $conn->real_escape_string($_POST['hora_fin']);
    $creado_por = (int)$_SESSION['id_usuario'];
    $departamentos = isset($_POST['departamentos']) ? $_POST['departamentos'] : [];
    
    $sql_insert = "INSERT INTO eventos (titulo, descripcion, id_cliente, fecha, hora_inicio, hora_fin, creado_por) 
                   VALUES ('$titulo', '$descripcion', $id_cliente, '$fecha', '$hora_inicio', '$hora_fin', $creado_por)";
    if ($conn->query($sql_insert)) {
        $evento_id = $conn->insert_id;
        foreach($departamentos as $dep_id) {
            $dep_id = (int)$dep_id;
            $conn->query("INSERT INTO evento_departamento (id_evento, id_departamento) VALUES ($evento_id, $dep_id)");
        }
        $_SESSION['mensaje'] = "Evento '$titulo' programado correctamente.";
    } else {
        $_SESSION['error'] = "Error al guardar el evento: " . $conn->error;
    }
    header("Location: eventos.php");
    exit();
}

// --------- PROCESAR FORMULARIO DE EDITAR EVENTO ---------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'editar') {
    $id = (int)$_POST['id_evento'];
    $titulo = $conn->real_escape_string($_POST['titulo']);
    $descripcion = $conn->real_escape_string($_POST['descripcion']);
    $id_cliente = !empty($_POST['id_cliente']) ? (int)$_POST['id_cliente'] : "NULL";
    $fecha = $conn->real_escape_string($_POST['fecha']);
    $hora_inicio = $conn->real_escape_string($_POST['hora_inicio']);
    $hora_fin = $conn->real_escape_string($_POST['hora_fin']);
    $departamentos = isset($_POST['departamentos']) ? $_POST['departamentos'] : [];
    
    $sql_update = "UPDATE eventos SET titulo='$titulo', descripcion='$descripcion', id_cliente=$id_cliente, fecha='$fecha', hora_inicio='$hora_inicio', hora_fin='$hora_fin' WHERE id=$id";
    if ($conn->query($sql_update)) {
        $conn->query("DELETE FROM evento_departamento WHERE id_evento = $id");
        foreach($departamentos as $dep_id) {
            $dep_id = (int)$dep_id;
            $conn->query("INSERT INTO evento_departamento (id_evento, id_departamento) VALUES ($id, $dep_id)");
        }
        $_SESSION['mensaje'] = "Evento actualizado correctamente.";
    } else {
        $_SESSION['error'] = "Error al actualizar el evento: " . $conn->error;
    }
    header("Location: eventos.php");
    exit();
}

// --------- PROCESAR ELIMINAR EVENTO ---------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'eliminar') {
    $id = (int)$_POST['id_evento'];
    if ($conn->query("DELETE FROM eventos WHERE id = $id")) {
        $_SESSION['mensaje'] = "Evento eliminado permanentemente.";
    } else {
        $_SESSION['error'] = "Error al eliminar el evento: " . $conn->error;
    }
    header("Location: eventos.php");
    exit();
}
// --------------------------------------------------------

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$dept_id = (int)$_SESSION['id_departamento'];

$sql = "SELECT e.*, u.nombre as autor, 
               cl.nombre as cliente_nombre, cl.cedula as cliente_cedula, cl.telefono as cliente_telefono,
               GROUP_CONCAT(d.nombre SEPARATOR ', ') as departamentos_asignados,
               GROUP_CONCAT(ed.id_departamento SEPARATOR ',') as ids_departamentos
        FROM eventos e 
        LEFT JOIN usuario u ON e.creado_por = u.id
        LEFT JOIN clientes cl ON e.id_cliente = cl.id
        LEFT JOIN evento_departamento ed ON e.id = ed.id_evento
        LEFT JOIN departamentos d ON ed.id_departamento = d.id";

$condiciones = array();

if ($search != '') {
    $condiciones[] = "(e.titulo LIKE '%$search%' OR e.descripcion LIKE '%$search%' OR cl.nombre LIKE '%$search%')";
}

if (!$is_global) {
    $condiciones[] = "e.id IN (SELECT id_evento FROM evento_departamento WHERE id_departamento = $dept_id)";
}

if (count($condiciones) > 0) {
    $sql .= " WHERE " . implode(" AND ", $condiciones);
}

$sql .= " GROUP BY e.id ORDER BY e.fecha DESC, e.hora_inicio ASC";
$result = $conn->query($sql);
$departamentos = $conn->query("SELECT * FROM departamentos");
$clientes = $conn->query("SELECT * FROM clientes ORDER BY nombre ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eventos - DN Producciones</title>
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
                    <li class="nav-item"><a class="nav-link active" href="eventos.php">Eventos</a></li>
                    <?php if($is_global): ?>
                    <li class="nav-item"><a class="nav-link" href="clientes.php">Clientes</a></li>
                    <li class="nav-item"><a class="nav-link" href="movimientos.php">Movimientos</a></li>
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
            <h2>Gestión de Eventos</h2>
            <?php if ($is_global): ?>
            <button class="btn btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoEvento">
                <i class="bi bi-plus-lg"></i> Programar Nuevo
            </button>
            <?php endif; ?>
        </div>
        
        <form method="GET" action="eventos.php" class="mb-4">
            <div class="input-group shadow-sm">
                <input type="text" name="search" class="form-control" placeholder="Buscar evento por título o descripción..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Buscar</button>
                <?php if($search != ''): ?>
                    <a href="eventos.php" class="btn btn-secondary">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle bg-white">
                <thead class="table-dark">
                    <tr>
                        <th>Fecha</th>
                        <th>Horario</th>
                        <th>Título</th>
                        <th>Cliente</th>
                        <th>Descripción</th>
                        <th>Departamentos</th>
                        <?php if ($is_global): ?>
                        <th class="text-center">Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            // Formateo de fecha y hora para mostrar
                            $fecha_str = date('d/m/Y', strtotime($row['fecha']));
                            $hora_ini_str = date('h:i A', strtotime($row['hora_inicio']));
                            $hora_fin_str = date('h:i A', strtotime($row['hora_fin']));
                            $deps_str = empty($row['departamentos_asignados']) ? '<i>Todos / Ninguno</i>' : $row['departamentos_asignados'];
                            $deps_ids = empty($row['ids_departamentos']) ? '' : $row['ids_departamentos'];
                            $cliente_str = !empty($row['cliente_nombre']) 
                                ? "<strong>" . htmlspecialchars($row['cliente_nombre']) . "</strong><br><small class='text-muted'>" . htmlspecialchars($row['cliente_cedula']) . " | " . htmlspecialchars($row['cliente_telefono']) . "</small>" 
                                : "<span class='text-muted'><i>Sin asignar</i></span>";

                            echo "<tr>
                                <td><strong>{$fecha_str}</strong></td>
                                <td>{$hora_ini_str} a {$hora_fin_str}</td>
                                <td>{$row['titulo']}</td>
                                <td>{$cliente_str}</td>
                                <td>{$row['descripcion']}</td>
                                <td><span class='badge bg-secondary'>{$deps_str}</span></td>";
                            if ($is_global) {
                                echo "<td class='text-center'>
                                    <button class='btn btn-primary btn-sm me-1' onclick='abrirModalEditarEvento(this)' 
                                        data-id='{$row['id']}'
                                        data-titulo='" . htmlspecialchars($row['titulo'], ENT_QUOTES) . "'
                                        data-desc='" . htmlspecialchars($row['descripcion'], ENT_QUOTES) . "'
                                        data-cliente='{$row['id_cliente']}'
                                        data-fecha='{$row['fecha']}'
                                        data-inicio='{$row['hora_inicio']}'
                                        data-fin='{$row['hora_fin']}'
                                        data-deps='{$deps_ids}' title='Editar'>
                                        <i class='bi bi-pencil'></i>
                                    </button>
                                    <button class='btn btn-danger btn-sm' onclick='confirmarEliminarEvento({$row['id']})' title='Eliminar'>
                                        <i class='bi bi-trash'></i>
                                    </button>
                                </td>";
                            }
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' class='text-center py-4 text-muted'>No se encontraron eventos programados.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Formulario Oculto para Eliminar Evento -->
    <form id="formEliminarEvento" method="POST" action="eventos.php" style="display:none;">
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="id_evento" id="inputEliminarEventoId">
    </form>

    <!-- Modal NUEVO EVENTO -->
    <div class="modal fade" id="modalNuevoEvento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="bi bi-calendar-plus"></i> Programar Nuevo Evento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="eventos.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="nuevo">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Título del Evento</label>
                                <input type="text" name="titulo" class="form-control" required placeholder="Ej: Concierto en el Estadio">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Cliente del Evento</label>
                                <select name="id_cliente" class="form-select">
                                    <option value="">Seleccione un cliente (Opcional)...</option>
                                    <?php 
                                    if ($clientes && $clientes->num_rows > 0) {
                                        $clientes->data_seek(0);
                                        while($cl = $clientes->fetch_assoc()): ?>
                                            <option value="<?php echo $cl['id']; ?>"><?php echo htmlspecialchars($cl['nombre'] . " (" . $cl['cedula'] . ")"); ?></option>
                                        <?php endwhile;
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="3" placeholder="Detalles, lugar, requerimientos..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Fecha</label>
                                <input type="date" name="fecha" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Hora de Inicio</label>
                                <input type="time" name="hora_inicio" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Hora de Fin</label>
                                <input type="time" name="hora_fin" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Departamentos Asignados (Opcional)</label><br>
                            <?php 
                            $departamentos->data_seek(0);
                            while($dep = $departamentos->fetch_assoc()): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="departamentos[]" value="<?php echo $dep['id']; ?>" id="dep_nuevo_<?php echo $dep['id']; ?>">
                                    <label class="form-check-label" for="dep_nuevo_<?php echo $dep['id']; ?>"><?php echo $dep['nombre']; ?></label>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Guardar Evento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal EDITAR EVENTO -->
    <div class="modal fade" id="modalEditarEvento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Editar Evento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="eventos.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id_evento" id="edit_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Título del Evento</label>
                                <input type="text" name="titulo" id="edit_titulo" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Cliente del Evento</label>
                                <select name="id_cliente" id="edit_cliente" class="form-select">
                                    <option value="">Seleccione un cliente (Opcional)...</option>
                                    <?php 
                                    if ($clientes && $clientes->num_rows > 0) {
                                        $clientes->data_seek(0);
                                        while($cl = $clientes->fetch_assoc()): ?>
                                            <option value="<?php echo $cl['id']; ?>"><?php echo htmlspecialchars($cl['nombre'] . " (" . $cl['cedula'] . ")"); ?></option>
                                        <?php endwhile;
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Descripción</label>
                            <textarea name="descripcion" id="edit_descripcion" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Fecha</label>
                                <input type="date" name="fecha" id="edit_fecha" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Hora de Inicio</label>
                                <input type="time" name="hora_inicio" id="edit_inicio" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Hora de Fin</label>
                                <input type="time" name="hora_fin" id="edit_fin" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Departamentos Asignados (Opcional)</label><br>
                            <?php 
                            $departamentos->data_seek(0);
                            while($dep = $departamentos->fetch_assoc()): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input edit-checkbox-dep" type="checkbox" name="departamentos[]" value="<?php echo $dep['id']; ?>" id="dep_edit_<?php echo $dep['id']; ?>">
                                    <label class="form-check-label" for="dep_edit_<?php echo $dep['id']; ?>"><?php echo $dep['nombre']; ?></label>
                                </div>
                            <?php endwhile; ?>
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
        function abrirModalEditarEvento(btn) {
            document.getElementById('edit_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_titulo').value = btn.getAttribute('data-titulo');
            document.getElementById('edit_descripcion').value = btn.getAttribute('data-desc');
            document.getElementById('edit_cliente').value = btn.getAttribute('data-cliente') || "";
            document.getElementById('edit_fecha').value = btn.getAttribute('data-fecha');
            document.getElementById('edit_inicio').value = btn.getAttribute('data-inicio');
            document.getElementById('edit_fin').value = btn.getAttribute('data-fin');
            
            const depsStr = btn.getAttribute('data-deps') || "";
            const depsArray = depsStr.split(',');
            document.querySelectorAll('.edit-checkbox-dep').forEach(cb => {
                cb.checked = depsArray.includes(cb.value);
            });
            
            var modalEdit = new bootstrap.Modal(document.getElementById('modalEditarEvento'));
            modalEdit.show();
        }

        function confirmarEliminarEvento(id) {
            if(confirm('¿Estás SEGURO de que deseas CANCELAR Y ELIMINAR permanentemente este evento?')) {
                document.getElementById('inputEliminarEventoId').value = id;
                document.getElementById('formEliminarEvento').submit();
            }
        }
    </script>
</body>
</html>
