<?php
$dir = "C:/xampp/htdocs/dnproducciones/";
$files = ['activos.php', 'eventos.php', 'inventario.php', 'usuarios.php', 'admin.php', 'login.php'];

foreach ($files as $file) {
    $path = $dir . $file;
    if (!file_exists($path)) continue;
    $content = file_get_contents($path);
    
    // Fix table names
    $content = str_replace('FROM usuarios ', 'FROM usuario ', $content);
    $content = str_replace('INTO usuarios ', 'INTO usuario ', $content);
    $content = str_replace('UPDATE usuarios ', 'UPDATE usuario ', $content);
    
    // Fix primary keys in SQL Aliases
    $content = str_replace('a.id_activo', 'a.id', $content);
    $content = str_replace('c.id_categoria', 'c.id', $content);
    $content = str_replace('d.id_departamento', 'd.id', $content);
    $content = str_replace('e.id_evento', 'e.id', $content);
    $content = str_replace('i.id_inventario', 'i.id', $content);
    $content = str_replace('u.id_usuario', 'u.id', $content);
    
    // Fix WHERE clauses for updates/deletes
    $content = str_replace('WHERE id_activo=', 'WHERE id=', $content);
    $content = str_replace('WHERE id_activo =', 'WHERE id =', $content);
    $content = str_replace('WHERE id_evento=', 'WHERE id=', $content);
    $content = str_replace('WHERE id_evento =', 'WHERE id =', $content);
    $content = str_replace('WHERE id_inventario=', 'WHERE id=', $content);
    $content = str_replace('WHERE id_inventario =', 'WHERE id =', $content);
    $content = str_replace('WHERE id_usuario=', 'WHERE id=', $content);
    $content = str_replace('WHERE id_usuario =', 'WHERE id =', $content);
    
    // Fix fetch assoc references
    $content = str_replace("\$row['id_activo']", "\$row['id']", $content);
    $content = str_replace("\$row['id_evento']", "\$row['id']", $content);
    $content = str_replace("\$row['id_inventario']", "\$row['id']", $content);
    $content = str_replace("\$row['id_usuario']", "\$row['id']", $content);
    
    // Fix login query specifically
    $content = preg_replace("/SELECT \* FROM usuario WHERE email = '\\\$email' AND password = '\\\$password'/", "SELECT u.*, r.name as rol_name FROM usuario u LEFT JOIN usuario_rol ur ON u.id = ur.id_usuario LEFT JOIN rol r ON ur.id_rol = r.id WHERE u.email = '\$email' LIMIT 1", $content);
    
    file_put_contents($path, $content);
}
echo "Refactor complete.\n";
?>
