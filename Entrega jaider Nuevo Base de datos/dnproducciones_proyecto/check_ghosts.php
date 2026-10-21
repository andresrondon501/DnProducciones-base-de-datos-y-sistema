<?php
$files = glob('C:/xampp/htdocs/dnproducciones/*.php');
$ghosts = ['id_activo', 'id_evento', 'id_inventario', 'id_usuario'];
foreach($files as $f) {
    $c = file_get_contents($f);
    foreach($ghosts as $g) {
        if (preg_match_all("/\\b$g\\b/", $c, $matches, PREG_OFFSET_CAPTURE)) {
            foreach($matches[0] as $m) {
                // Get the context of the match (e.g. 20 chars before and after)
                $context = substr($c, max(0, $m[1] - 30), 60);
                echo basename($f) . ": Found '$g' in context: '$context'\n";
            }
        }
    }
}
?>
