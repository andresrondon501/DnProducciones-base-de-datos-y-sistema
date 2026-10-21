<?php
$c = mysqli_connect('127.0.0.1','root','');
if(!$c) {
    echo "Connection failed: " . mysqli_connect_error();
} else {
    echo "Connected successfully to server.\n";
    if(mysqli_select_db($c, 'dnproducciones')) {
        echo "Selected dnproducciones successfully.\n";
    } else {
        echo "Failed to select dnproducciones: " . mysqli_error($c);
    }
}
?>
