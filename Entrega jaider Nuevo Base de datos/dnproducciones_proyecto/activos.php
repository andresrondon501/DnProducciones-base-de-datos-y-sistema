<?php
session_start();
require 'db.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

// --------- PROCESAR FORMULARIO DE NUEVO ACTIVO ---------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'nuevo') {
    $codigo = $conn->real_escape_string($_POST['codigo']);
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $descripcion = $conn->real_escape_string($_POST['descripcion']);
    $id_categoria = (int)$_POST['id_categoria'];
    $id_departamento = in_array($_SESSION['rol'], ['admin', 'jefe_general']) ? (int)$_POST['id_departamento'] : (int)$_SESSION['id_departamento'];
    $estado = $conn->real_escape_string($_POST['estado']);
    $fecha_compra = $conn->real_escape_string($_POST['fecha_compra']);
    
    $check = $conn->query("SELECT id FROM activos WHERE codigo = '$codigo'");
    if ($check->num_rows > 0) {
        $_SESSION['error'] = "El código '$codigo' ya está registrado en otro activo.";
    } else {
        $sql_insert = "INSERT INTO activos (codigo, nombre, descripcion, id_categoria, id_departamento, estado, fecha_compra) 
                       VALUES ('$codigo', '$nombre', '$descripcion', $id_categoria, $id_departamento, '$estado', '$fecha_compra')";
        if ($conn->query($sql_insert)) {
            $_SESSION['mensaje'] = "Activo '$nombre' agregado correctamente.";
        } else {
            $_SESSION['error'] = "Error al guardar: " . $conn->error;
        }
    }
    header("Location: activos.php");
    exit();
}

// --------- PROCESAR FORMULARIO DE EDITAR ACTIVO ---------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'editar') {
    $id = (int)$_POST['id_activo'];
    $codigo = $conn->real_escape_string($_POST['codigo']);
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $descripcion = $conn->real_escape_string($_POST['descripcion']);
    $id_categoria = (int)$_POST['id_categoria'];
    $id_departamento = in_array($_SESSION['rol'], ['admin', 'jefe_general']) ? (int)$_POST['id_departamento'] : (int)$_SESSION['id_departamento'];
    $estado = $conn->real_escape_string($_POST['estado']);
    $fecha_compra = $conn->real_escape_string($_POST['fecha_compra']);
    
    // Verifica que el código nuevo no choque con otro equipo existente
    $check = $conn->query("SELECT id FROM activos WHERE codigo = '$codigo' AND id != $id");
    if ($check->num_rows > 0) {
        $_SESSION['error'] = "El código '$codigo' ya pertenece a otro equipo.";
    } else {
        $sql_update = "UPDATE activos SET codigo='$codigo', nombre='$nombre', descripcion='$descripcion', id_categoria=$id_categoria, id_departamento=$id_departamento, estado='$estado', fecha_compra='$fecha_compra' WHERE id=$id";
        if ($conn->query($sql_update)) {
            $_SESSION['mensaje'] = "Activo actualizado correctamente.";
        } else {
            $_SESSION['error'] = "Error al actualizar: " . $conn->error;
        }
    }
    header("Location: activos.php");
    exit();
}

// --------- PROCESAR ELIMINAR ACTIVO ---------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'eliminar') {
    $id = (int)$_POST['id_activo'];
    if ($conn->query("DELETE FROM activos WHERE id = $id")) {
        $_SESSION['mensaje'] = "Activo eliminado permanentemente.";
    } else {
        $_SESSION['error'] = "Error al eliminar: " . $conn->error;
    }
    header("Location: activos.php");
    exit();
}
// --------------------------------------------------------

// Listas para los dropdowns
$categorias = $conn->query("SELECT * FROM categorias");
$departamentos = $conn->query("SELECT * FROM departamentos");

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

$sql = "SELECT a.*, c.nombre as categoria_nombre, d.nombre as departamento_nombre 
        FROM activos a 
        LEFT JOIN categorias c ON a.id_categoria = c.id 
        LEFT JOIN departamentos d ON a.id_departamento = d.id";

$is_global = in_array($_SESSION['rol'], ['admin', 'jefe_general']);
$dept_id = (int)$_SESSION['id_departamento'];

$condiciones = array();

if ($search != '') {
    $condiciones[] = "(a.codigo LIKE '%$search%' OR a.nombre LIKE '%$search%' OR c.nombre LIKE '%$search%')";
}

if (!$is_global) {
    $condiciones[] = "a.id_departamento = $dept_id";
}

if (count($condiciones) > 0) {
    $sql .= " WHERE " . implode(" AND ", $condiciones);
}

$sql .= " ORDER BY a.id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activos - DN Producciones</title>
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
                    <li class="nav-item"><a class="nav-link active" href="activos.php">Activos</a></li>
                    <li class="nav-item"><a class="nav-link" href="inventario.php">Inventario</a></li>
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
            <h2>Gestión de Activos</h2>
            <button class="btn btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoActivo">
                <i class="bi bi-plus-lg"></i> Agregar Nuevo
            </button>
        </div>
        
        <form method="GET" action="activos.php" class="mb-4">
            <div class="input-group shadow-sm">
                <input type="text" name="search" class="form-control" placeholder="Buscar equipo por código, nombre o categoría..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Buscar</button>
                <?php if($search != ''): ?>
                    <a href="activos.php" class="btn btn-secondary">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle bg-white">
                <thead class="table-dark">
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Departamento</th>
                        <th>Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            $badge_class = 'bg-secondary';
                            if($row['estado'] == 'Disponible') $badge_class = 'bg-success';
                            if($row['estado'] == 'En uso') $badge_class = 'bg-primary';
                            if($row['estado'] == 'En mantenimiento') $badge_class = 'bg-warning text-dark';
                            if($row['estado'] == 'Dañado') $badge_class = 'bg-danger';

                            // Guardamos todos los datos ocultos en el radio button para poder llenar el formulario de Editar
                            echo "<tr>
                                <td><strong>{$row['codigo']}</strong></td>
                                <td>{$row['nombre']}</td>
                                <td>{$row['categoria_nombre']}</td>
                                <td>{$row['departamento_nombre']}</td>
                                <td><span class='badge {$badge_class}'>{$row['estado']}</span></td>
                                <td class='text-center'>
                                    <button class='btn btn-primary btn-sm me-1' onclick='abrirModalEditarActivo(this)' 
                                        data-id='{$row['id']}'
                                        data-codigo='{$row['codigo']}'
                                        data-nombre='{$row['nombre']}'
                                        data-desc='{$row['descripcion']}'
                                        data-categoria='{$row['id_categoria']}'
                                        data-departamento='{$row['id_departamento']}'
                                        data-estado='{$row['estado']}'
                                        data-fecha='{$row['fecha_compra']}' title='Editar'>
                                        <i class='bi bi-pencil'></i>
                                    </button>
                                    <button class='btn btn-danger btn-sm' onclick='confirmarEliminarActivo({$row['id']})' title='Eliminar'>
                                        <i class='bi bi-trash'></i>
                                    </button>
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center py-4 text-muted'>No se encontraron activos con esa búsqueda.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Formulario Oculto para Eliminar -->
    <form id="formEliminar" method="POST" action="activos.php" style="display:none;">
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="id_activo" id="inputEliminarId">
    </form>

    <!-- Modal NUEVO ACTIVO -->
    <div class="modal fade" id="modalNuevoActivo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Agregar Nuevo Activo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="activos.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="nuevo">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Código del Equipo</label>
                                <input type="text" name="codigo" class="form-control" required placeholder="Ej: PAN-02">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Nombre</label>
                                <input type="text" name="nombre" class="form-control" required placeholder="Ej: Pantalla LED 55 pulgadas">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="2" placeholder="Detalles del equipo..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Categoría</label>
                                <select name="id_categoria" class="form-select" required>
                                    <option value="">Seleccione una categoría...</option>
                                    <?php 
                                    $categorias->data_seek(0);
                                    while($cat = $categorias->fetch_assoc()) echo "<option value='{$cat['id']}'>{$cat['nombre']}</option>";
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3" <?php if(!$is_global) echo 'style="display:none;"'; ?>>
                                <label class="form-label fw-bold">Departamento Asignado</label>
                                <select name="id_departamento" class="form-select" <?php echo $is_global ? 'required' : ''; ?>>
                                    <option value="">Seleccione un departamento...</option>
                                    <?php 
                                    $departamentos->data_seek(0);
                                    while($dep = $departamentos->fetch_assoc()) echo "<option value='{$dep['id']}'>{$dep['nombre']}</option>";
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Estado Inicial</label>
                                <select name="estado" class="form-select" required>
                                    <option value="Disponible" selected>Disponible</option>
                                    <option value="En uso">En uso</option>
                                    <option value="En mantenimiento">En mantenimiento</option>
                                    <option value="Dañado">Dañado</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Fecha de Compra / Registro</label>
                                <input type="date" name="fecha_compra" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Guardar Activo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal EDITAR ACTIVO -->
    <div class="modal fade" id="modalEditarActivo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Editar Activo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="activos.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id_activo" id="edit_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Código del Equipo</label>
                                <input type="text" name="codigo" id="edit_codigo" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Nombre</label>
                                <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Descripción</label>
                            <textarea name="descripcion" id="edit_descripcion" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Categoría</label>
                                <select name="id_categoria" id="edit_categoria" class="form-select" required>
                                    <option value="">Seleccione una categoría...</option>
                                    <?php 
                                    $categorias->data_seek(0);
                                    while($cat = $categorias->fetch_assoc()) echo "<option value='{$cat['id']}'>{$cat['nombre']}</option>";
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3" <?php if(!$is_global) echo 'style="display:none;"'; ?>>
                                <label class="form-label fw-bold">Departamento Asignado</label>
                                <select name="id_departamento" id="edit_departamento" class="form-select" <?php echo $is_global ? 'required' : ''; ?>>
                                    <option value="">Seleccione un departamento...</option>
                                    <?php 
                                    $departamentos->data_seek(0);
                                    while($dep = $departamentos->fetch_assoc()) echo "<option value='{$dep['id']}'>{$dep['nombre']}</option>";
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Estado</label>
                                <select name="estado" id="edit_estado" class="form-select" required>
                                    <option value="Disponible">Disponible</option>
                                    <option value="En uso">En uso</option>
                                    <option value="En mantenimiento">En mantenimiento</option>
                                    <option value="Dañado">Dañado</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Fecha</label>
                                <input type="date" name="fecha_compra" id="edit_fecha" class="form-control" required>
                            </div>
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
        function abrirModalEditarActivo(btn) {
            document.getElementById('edit_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_codigo').value = btn.getAttribute('data-codigo');
            document.getElementById('edit_nombre').value = btn.getAttribute('data-nombre');
            document.getElementById('edit_descripcion').value = btn.getAttribute('data-desc');
            document.getElementById('edit_categoria').value = btn.getAttribute('data-categoria');
            document.getElementById('edit_departamento').value = btn.getAttribute('data-departamento');
            document.getElementById('edit_estado').value = btn.getAttribute('data-estado');
            document.getElementById('edit_fecha').value = btn.getAttribute('data-fecha');
            
            var modalEdit = new bootstrap.Modal(document.getElementById('modalEditarActivo'));
            modalEdit.show();
        }

        function confirmarEliminarActivo(id) {
            if(confirm('¿Estás SEGURO de que deseas ELIMINAR permanentemente este equipo? Esta acción no se puede deshacer.')) {
                document.getElementById('inputEliminarId').value = id;
                document.getElementById('formEliminar').submit();
            }
        }
    </script>
</body>
</html>
