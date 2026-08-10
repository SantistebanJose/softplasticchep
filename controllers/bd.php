<?php
//bd.php
function conectar_oll_BD() {
    $host = "bi.back-mrsoft.com";
    $user = "usrwebapp";
    $password = '004a058a0c7e5bcbad23ea603529e66f65935f2c14245a12e7a7d10821be89ca';
    $port = "5432";
    $nombreBD = "bd_restaurante";

    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$nombreBD";
        $conexion = new PDO($dsn, $user, $password);
        $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        //echo "conectadoo :)";
        return $conexion;
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }

}