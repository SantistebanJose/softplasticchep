<?php
function executeQuery(PDO $conexion, string $query, array $params = []): array
{
    try {
        $orden = $conexion->prepare($query);
        foreach ($params as $clave => $valor) {
            $tipo = is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $orden->bindValue(is_int($clave) ? $clave + 1 : ':' . ltrim($clave, ':'), $valor, $tipo);
        }
        $orden->execute();
        $datos = $orden->fetchAll(PDO::FETCH_ASSOC);
        $orden->closeCursor();

        return $datos;
    } catch (PDOException $e) {
        error_log("Error en la base de datos: " . $e->getMessage());
        return [];
    }
}


?>