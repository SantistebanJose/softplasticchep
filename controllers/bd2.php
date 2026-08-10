


<?php
//bd.php
function conectar_oll_BD() {
    //$host = "bi.back-mrsoft.com";
    //$user = "usrweb";
    //$password = 'admin-Captaian*1278871/&%561652';
    //$port = "5432";
    //$nombreBD = "bdplasticche";
    $server = "localhost"; // O la IP del servidor PostgreSQL
    $bd = "bdplasticche";      // Nombre de la base de datos
    $user = "postgres";    // Usuario de PostgreSQL (por defecto es "postgres")
    $pass = "76008509"; // Contraseña del usuario
    $port = "5432";        // Puerto de PostgreSQL (por defecto es 5432)

    try {
        $dsn = "pgsql:host=$server;port=$port;dbname=$bd";
        $conexion = new PDO($dsn, $user, $pass);
        $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        //echo "conectadoo :)";
        return $conexion;
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }

}






//conectarBD();