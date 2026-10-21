<?php
$files = ['C:/xampp/htdocs/dnproducciones/activos.php', 'C:/xampp/htdocs/dnproducciones/inventario.php'];
foreach ($files as $path) {
    $content = file_get_contents($path);
    // Fix category option loops
    $content = str_replace("value='{\$cat['id_categoria']}'", "value='{\$cat['id']}'", $content);
    // Fix department option loops
    $content = str_replace("value='{\$dep['id_departamento']}'", "value='{\$dep['id']}'", $content);
    
    file_put_contents($path, $content);
}
echo "Fixed dropdown values.";
?>
