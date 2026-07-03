<?php
//bd_oll.php
function conectar_oll_BD() {
    $host = "bi.back-mrsoft.com";
    $user = "usrweb";
    $password = 'admin-Captaian*1278871/&%561652';
    $port = "5432";
    $nombreBD = "bdplasticche";

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



//conectarBD();