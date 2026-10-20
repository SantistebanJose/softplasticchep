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

/**
 * Descuenta stock de forma atómica dentro de la transacción del llamador.
 * PostgreSQL bloquea la fila mientras evalúa y aplica el UPDATE; si otra
 * petición consumió antes, la condición stock_actual >= cantidad se vuelve
 * a evaluar con el saldo actualizado y el descuento no se realiza.
 *
 * @return array{nombre:string, stock_actual:string} material y saldo restante
 */
function descontarStockMaterial(PDO $conexion, int $materialId, float $cantidad): array
{
    $cantidad = round($cantidad, 4);
    if ($materialId <= 0 || $cantidad <= 0) {
        throw new InvalidArgumentException('El material y la cantidad para descontar deben ser válidos.');
    }

    $actualizado = executeQuery($conexion, "
        UPDATE material
        SET stock_actual = stock_actual - :cantidad
        WHERE id = :id
          AND stock_actual >= :cantidad
        RETURNING nombre, stock_actual
    ", ['cantidad' => $cantidad, 'id' => $materialId]);

    if (!empty($actualizado)) {
        return $actualizado[0];
    }

    $material = executeQuery($conexion, "
        SELECT nombre, stock_actual
        FROM material
        WHERE id = :id
    ", ['id' => $materialId]);

    if (empty($material)) {
        throw new RuntimeException("Material #$materialId no encontrado.");
    }

    $disponible = (float)($material[0]['stock_actual'] ?? 0);
    throw new RuntimeException(
        'Stock insuficiente de "' . $material[0]['nombre'] . '". Disponible: '
        . number_format($disponible, 4, '.', '') . ', solicitado: '
        . number_format($cantidad, 4, '.', '') . '.'
    );
}
