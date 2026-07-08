<?php

/**
 * controllers/executeQuery.php
 *
 * IMPORTANTE (fix):
 * Antes esta función se usaba tanto para SELECT como para INSERT/UPDATE/DELETE,
 * y cualquier PDOException se atrapaba y se devolvía como [] (array vacío),
 * lo cual hacía que el código que llamaba a executeQuery() NUNCA se enterara
 * de que la consulta había fallado (por eso el sistema decía "guardado
 * correctamente" sin haber guardado nada).
 *
 * Ahora:
 *  - executeQuery()    -> para SELECT. Devuelve las filas. Deja que las
 *                         excepciones se propaguen hacia arriba.
 *  - executeNonQuery()  -> para INSERT/UPDATE/DELETE. Devuelve cuántas filas
 *                         fueron afectadas. También deja propagar excepciones.
 *
 * Quien llame a estas funciones (el controlador) debe envolver la llamada en
 * try/catch si quiere responder un mensaje de error controlado, o dejar que
 * suba hasta el try/catch general de clssProveedor.php.
 */

function bindParams(PDOStatement $orden, array $params): void
{
    foreach ($params as $clave => $valor) {
        $nombre = is_int($clave) ? $clave + 1 : ':' . ltrim($clave, ':');

        if ($valor === null) {
            $orden->bindValue($nombre, null, PDO::PARAM_NULL);
        } elseif (is_bool($valor)) {
            $orden->bindValue($nombre, $valor, PDO::PARAM_BOOL);
        } elseif (is_int($valor)) {
            $orden->bindValue($nombre, $valor, PDO::PARAM_INT);
        } else {
            $orden->bindValue($nombre, (string) $valor, PDO::PARAM_STR);
        }
    }
}

/**
 * Para SELECT. Devuelve un array de filas (puede ser [] si no hay resultados,
 * pero eso ahora significa "no hay resultados", no "hubo un error oculto").
 */
function executeQuery(PDO $conexion, string $query, array $params = []): array
{
    $orden = $conexion->prepare($query);
    bindParams($orden, $params);
    $orden->execute();
    $datos = $orden->fetchAll(PDO::FETCH_ASSOC);
    $orden->closeCursor();

    return $datos;
}

/**
 * Para INSERT / UPDATE / DELETE. Devuelve la cantidad de filas afectadas.
 * Si la consulta falla, PDO lanza PDOException (porque ATTR_ERRMODE está en
 * ERRMODE_EXCEPTION en bd.php) y esa excepción sube hasta quien llamó a esta
 * función, en vez de perderse en silencio.
 */
function executeNonQuery(PDO $conexion, string $query, array $params = []): int
{
    $orden = $conexion->prepare($query);
    bindParams($orden, $params);
    $orden->execute();
    $filas = $orden->rowCount();
    $orden->closeCursor();

    return $filas;
}