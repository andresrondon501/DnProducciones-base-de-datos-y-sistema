<?php
$host = "127.0.0.1";
$user = "root";
$pass = ""; // Por defecto en XAMPP es vacío
$dbname = "dnproducciones";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
$conn->set_charset("utf8");
?>
