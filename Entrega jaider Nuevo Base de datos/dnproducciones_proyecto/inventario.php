<?php
session_start();
require 'db.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

// --------- PROCESAR FORMULARIO DE NUEVO INVENTARIO ---------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'nuevo') {
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $descripcion = $conn->real_escape_string($_POST['descripcion']);
    $cantidad = (int)$_POST['cantidad'];
    $id_departamento = in_array($_SESSION['rol'], ['admin', 'jefe_general']) ? (int)$_POST['id_departamento'] : (int)$_SESSION['id_departamento'];
    
    $sql_insert = "INSERT INTO inventario (nombre, descripcion, cantidad, id_departamento) 
                   VALUES ('$nombre', '$descripcion', $cantidad, $id_departamento)";
    if ($conn->query($sql_insert)) {
        $_SESSION['mensaje'] = "Ítem '$nombre' agregado correctamente al inventario.";
    } else {
        $_SESSION['error'] = "Error al guardar: " . $conn->error;
    }
    header("Location: inventario.php");
    exit();
}

// --------- PROCESAR FORMULARIO DE EDITAR INVENTARIO ---------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'editar') {
    $id = (int)$_POST['id_inventario'];
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $descripcion = $conn->real_escape_string($_POST['descripcion']);
    $cantidad = (int)$_POST['cantidad'];
    $id_departamento = in_array($_SESSION['rol'], ['admin', 'jefe_general']) ? (int)$_POST['id_departamento'] : (int)$_SESSION['id_departamento'];
    
    $sql_update = "UPDATE inventario SET nombre='$nombre', descripcion='$descripcion', cantidad=$cantidad, id_departamento=$id_departamento WHERE id=$id";
    if ($conn->query($sql_update)) {
        $_SESSION['mensaje'] = "Ítem actualizado correctamente.";
    } else {
        $_SESSION['error'] = "Error al actualizar: " . $conn->error;
    }
    header("Location: inventario.php");
    exit();
}

// --------- PROCESAR ELIMINAR INVENTARIO ---------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'eliminar') {
    $id = (int)$_POST['id_inventario'];
    if ($conn->query("DELETE FROM inventario WHERE id = $id")) {
        $_SESSION['mensaje'] = "Ítem eliminado permanentemente.";
    } else {
        $_SESSION['error'] = "Error al eliminar: " . $conn->error;
    }
    header("Location: inventario.php");
    exit();
}
// --------------------------------------------------------

$departamentos = $conn->query("SELECT * FROM departamentos");

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

$sql = "SELECT i.*, d.nombre as departamento_nombre 
        FROM inventario i 
        LEFT JOIN departamentos d ON i.id_departamento = d.id";

$is_global = in_array($_SESSION['rol'], ['admin', 'jefe_general']);
$dept_id = (int)$_SESSION['id_departamento'];

$condiciones = array();

if ($search != '') {
    $condiciones[] = "(i.nombre LIKE '%$search%' OR i.descripcion LIKE '%$search%')";
}

if (!$is_global) {
    $condiciones[] = "i.id_departamento = $dept_id";
}

if (count($condiciones) > 0) {
    $sql .= " WHERE " . implode(" AND ", $condiciones);
}

$sql .= " ORDER BY i.id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario - DN Producciones</title>
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
                    <li class="nav-item"><a class="nav-link active" href="inventario.php">Inventario</a></li>
                    <li class="nav-item"><a class="nav-link" href="eventos.php">Eventos</a></li>
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
            <h2>Inventario General (Insumos / Cables)</h2>
            <button class="btn btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoInventario">
                <i class="bi bi-plus-lg"></i> Agregar Ítem
            </button>
        </div>
        
        <form method="GET" action="inventario.php" class="mb-4">
            <div class="input-group shadow-sm">
                <input type="text" name="search" class="form-control" placeholder="Buscar insumos por nombre..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Buscar</button>
                <?php if($search != ''): ?>
                    <a href="inventario.php" class="btn btn-secondary">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle bg-white">
                <thead class="table-dark">
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Cantidad Disponible</th>
                        <th>Departamento</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            $badge_class = ($row['cantidad'] > 0) ? 'bg-success' : 'bg-danger';

                            echo "<tr>
                                <td><strong>{$row['nombre']}</strong></td>
                                <td>{$row['descripcion']}</td>
                                <td><span class='badge {$badge_class} px-3 py-2' style='font-size:1.1em;'>{$row['cantidad']}</span></td>
                                <td>{$row['departamento_nombre']}</td>
                                <td class='text-center'>
                                    <button class='btn btn-primary btn-sm me-1' onclick='abrirModalEditarInv(this)' 
                                        data-id='{$row['id']}'
                                        data-nombre='{$row['nombre']}'
                                        data-desc='{$row['descripcion']}'
                                        data-cantidad='{$row['cantidad']}'
                                        data-dep='{$row['id_departamento']}' title='Editar'>
                                        <i class='bi bi-pencil'></i>
                                    </button>
                                    <button class='btn btn-danger btn-sm' onclick='confirmarEliminarInv({$row['id']})' title='Eliminar'>
                                        <i class='bi bi-trash'></i>
                                    </button>
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center py-4 text-muted'>No se encontraron ítems en el inventario.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Formulario Oculto para Eliminar -->
    <form id="formEliminarInv" method="POST" action="inventario.php" style="display:none;">
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="id_inventario" id="inputEliminarInvId">
    </form>

    <!-- Modal NUEVO INVENTARIO -->
    <div class="modal fade" id="modalNuevoInventario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="bi bi-box-seam"></i> Agregar Ítem al Inventario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="inventario.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="nuevo">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nombre del Ítem</label>
                            <input type="text" name="nombre" class="form-control" required placeholder="Ej: Cable XLR de 10m">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Descripción (Opcional)</label>
                            <textarea name="descripcion" class="form-control" rows="2" placeholder="Detalles extra..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label fw-bold">Cantidad</label>
                                <input type="number" name="cantidad" class="form-control" required min="0" value="1">
                            </div>
                            <div class="col-6 mb-3" <?php if(!$is_global) echo 'style="display:none;"'; ?>>
                                <label class="form-label fw-bold">Departamento</label>
                                <select name="id_departamento" class="form-select" <?php echo $is_global ? 'required' : ''; ?>>
                                    <option value="">Seleccione...</option>
                                    <?php 
                                    $departamentos->data_seek(0);
                                    while($dep = $departamentos->fetch_assoc()) echo "<option value='{$dep['id']}'>{$dep['nombre']}</option>";
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Guardar Ítem</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal EDITAR INVENTARIO -->
    <div class="modal fade" id="modalEditarInventario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Editar Ítem</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="inventario.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id_inventario" id="edit_id">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nombre del Ítem</label>
                            <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Descripción (Opcional)</label>
                            <textarea name="descripcion" id="edit_descripcion" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label fw-bold">Cantidad</label>
                                <input type="number" name="cantidad" id="edit_cantidad" class="form-control" required min="0">
                            </div>
                            <div class="col-6 mb-3" <?php if(!$is_global) echo 'style="display:none;"'; ?>>
                                <label class="form-label fw-bold">Departamento</label>
                                <select name="id_departamento" id="edit_departamento" class="form-select" <?php echo $is_global ? 'required' : ''; ?>>
                                    <option value="">Seleccione...</option>
                                    <?php 
                                    $departamentos->data_seek(0);
                                    while($dep = $departamentos->fetch_assoc()) echo "<option value='{$dep['id']}'>{$dep['nombre']}</option>";
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function abrirModalEditarInv(btn) {
            document.getElementById('edit_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_nombre').value = btn.getAttribute('data-nombre');
            document.getElementById('edit_descripcion').value = btn.getAttribute('data-desc');
            document.getElementById('edit_cantidad').value = btn.getAttribute('data-cantidad');
            document.getElementById('edit_departamento').value = btn.getAttribute('data-dep');
            
            var modalEdit = new bootstrap.Modal(document.getElementById('modalEditarInventario'));
            modalEdit.show();
        }

        function confirmarEliminarInv(id) {
            if(confirm('¿Estás SEGURO de que deseas ELIMINAR permanentemente este ítem del inventario?')) {
                document.getElementById('inputEliminarId').value = id;
                document.getElementById('formEliminar').submit();
            }
        }
    </script>
</body>
</html>
