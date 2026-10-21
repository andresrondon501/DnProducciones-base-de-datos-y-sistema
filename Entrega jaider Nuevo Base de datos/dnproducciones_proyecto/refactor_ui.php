<?php
$files = [
    'activos.php' => [
        'title' => 'Gestión de Activos',
        'modal' => '#modalNuevoActivo',
        'id_var' => 'id_activo',
        'radio' => 'radio_activo',
        'eliminar_form' => 'formEliminar',
        'eliminar_input' => 'inputEliminarId',
        'colspan' => '6'
    ],
    'inventario.php' => [
        'title' => 'Inventario',
        'modal' => '#modalNuevoInventario',
        'id_var' => 'id_inventario',
        'radio' => 'radio_inventario',
        'eliminar_form' => 'formEliminar',
        'eliminar_input' => 'inputEliminarId',
        'colspan' => '5'
    ],
    'eventos.php' => [
        'title' => 'Gestión de Eventos',
        'modal' => '#modalNuevoEvento',
        'id_var' => 'id_evento',
        'radio' => 'radio_evento',
        'eliminar_form' => 'formEliminarEvento',
        'eliminar_input' => 'inputEliminarEventoId',
        'colspan' => '5'
    ],
    'usuarios.php' => [
        'title' => 'Gestión de Usuarios',
        'modal' => '#modalNuevoUsuario',
        'id_var' => 'id_usuario',
        'radio' => 'radio_usuario',
        'eliminar_form' => 'formEliminarUsuario',
        'eliminar_input' => 'inputEliminarUsuarioId',
        'colspan' => '6'
    ]
];

foreach ($files as $file => $config) {
    $path = "C:/xampp/htdocs/dnproducciones/" . $file;
    $content = file_get_contents($path);

    // 1. Replace the header dropdown with a "Nuevo" button
    // The dropdown usually starts with <div class="dropdown"> after the <h2>
    $btn_nuevo = '<button class="btn btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="'.$config['modal'].'"><i class="bi bi-plus-lg"></i> Agregar Nuevo</button>';
    
    // Replace the entire dropdown block
    $content = preg_replace(
        '/<div class="dropdown">.*?<\/ul>\s*<\/div>/is',
        $btn_nuevo,
        $content
    );

    // For eventos.php we wrapped the dropdown in <?php if($is_global): ? >
    // So we need to make sure the new button is also protected if needed.
    // In eventos.php, only global can add events. So it's already inside <?php if($is_global): ? >
    
    // 2. Change the table header from # to Acciones (put Acciones at the end)
    // Actually the user said "al lado quiero que pongas solamente un botón de editar y un botón rojo". 
    // Usually actions are at the right end.
    // Let's replace the <th scope="col" style="width: 50px;" class="text-center">#</th> with nothing, and add <th class="text-center">Acciones</th> at the end.
    
    // Wait, the radio button cell contains all the data-* attributes. 
    // We can extract it.
    
    file_put_contents($path, $content);
}
echo "UI Refactor applied.\n";
?>
