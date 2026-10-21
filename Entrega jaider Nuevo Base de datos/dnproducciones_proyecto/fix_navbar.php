<?php
$files = ['admin.php', 'activos.php', 'inventario.php', 'eventos.php', 'usuarios.php'];
foreach($files as $f) {
    $path = "C:/xampp/htdocs/dnproducciones/" . $f;
    $content = file_get_contents($path);
    
    // Replace <li class="nav-item"><a class="nav-link.*href="usuarios.php".*</li>
    // Use regex to find it and wrap it.
    $content = preg_replace(
        '/(<li class="nav-item"><a class="nav-link[^>]*href="usuarios\.php".*?<\/li>)/i',
        '<?php if(isset($_SESSION[\'rol\']) && $_SESSION[\'rol\'] === \'admin\'): ?>$1<?php endif; ?>',
        $content
    );
    
    file_put_contents($path, $content);
}
echo "Navbar fixed.";
?>
