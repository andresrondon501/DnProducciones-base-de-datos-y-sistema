<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT u.*, r.nombre_rol as rol_name 
            FROM usuario u 
            LEFT JOIN r_usuario_rol ur ON u.id = ur.usuario_id 
            LEFT JOIN rol r ON ur.rol_id = r.id 
            WHERE u.email = '$email' AND u.password_hash = '$password' LIMIT 1";
            
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        $_SESSION['id_usuario'] = $user['id'];
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['rol'] = $user['rol_name'];
        $_SESSION['id_departamento'] = $user['id_departamento'];

        // Registrar visita en auditoria_logs para control de accesos semanal/mensual del Admin
        $uid_log = (int)$user['id'];
        $conn->query("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, fecha) VALUES ($uid_log, 'Inicio de sesión (Visita a la aplicación)', 'usuario', NOW())");

        // Todos van al dashboard, el dashboard filtrará lo que ven
        header("Location: admin.php");
        exit();
    } else {
        $_SESSION['error'] = "Credenciales incorrectas o correo no encontrado.";
        header("Location: index.php");
    }
}
?>
