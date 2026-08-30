<?php
/**
 * controllers_tablet/clssAuthOperario.php
 * Login rápido de operarios en tablet: identificación solo por DNI (sin password).
 * Reutiliza la tabla `usuario` (user_ = DNI del operario), solo permite rol = 'operario'.
 *
 * NUEVO: al loguear también se resuelven y guardan en sesión las etapas del
 * operario (operario.js_etapas_relacionadas), para poder controlar el acceso
 * a cada módulo tablet (Producción / Ensamblaje / Empaquetado) según a qué
 * etapas está realmente asignado, en vez de dejar entrar a cualquier
 * operario logueado a cualquier página solo por tener sesión activa.
 *
 * FIX: las etapas se resolvían buscando en `operario` por usuario.id, pero
 * `usuario` y `operario` son secuencias independientes — la relación real
 * es usuario.operario_id (FK) -> operario.id. Usar usuario.id directamente
 * hacía que, cuando ambos ids se desalineaban, el login leyera las etapas
 * de OTRO operario (el que casualmente tuviera ese mismo id en la tabla
 * operario), dejando al usuario real con acceso incorrecto o incompleto.
 */

require_once __DIR__ . '/../controllers/bd.php';
require_once __DIR__ . '/../controllers/executeQuery.php';

function intentarLoginOperario(string $dni): array
{
    $dni = trim($dni);

    if ($dni === '' || !ctype_digit($dni) || strlen($dni) !== 8) {
        return ['success' => false, 'error' => 'Ingresa un DNI válido de 8 dígitos.'];
    }

    $conectar = conectar_oll_BD();

    $result = executeQuery(
        $conectar,
        "SELECT id, operario_id, user_, nombre_completo, rol_y_perfiles, deleted_at
         FROM usuario
         WHERE user_ = :user_",
        ['user_' => $dni]
    );

    if (empty($result)) {
        return ['success' => false, 'error' => 'DNI no registrado.'];
    }

    $usuario = $result[0];

    if ($usuario['deleted_at'] !== null) {
        return ['success' => false, 'error' => 'Usuario desactivado. Contacta a un administrador.'];
    }

    $rolPerfiles = decodificarRolPerfiles($usuario['rol_y_perfiles']);
    $rol = strtolower(trim((string) ($rolPerfiles['rol'] ?? '')));

    if ($rol !== 'operario') {
        return ['success' => false, 'error' => 'Este DNI no corresponde a un operario.'];
    }

    // NUEVO: se resuelven las etapas asignadas a este operario en la tabla
    // `operario` (misma fila que usa el resto del sistema para
    // js_etapas_relacionadas / js_sucursales), usando usuario.operario_id
    // (FK real hacia operario.id) y NO usuario.id. Si el usuario no tiene
    // operario_id vinculado, simplemente queda sin etapas (sin acceso a
    // ningún módulo) hasta que un admin lo configure.
    $etapas = resolverEtapasOperario($conectar, (int) ($usuario['operario_id'] ?? 0));

    session_regenerate_id(true);
    guardarSesionOperario($usuario, $etapas);

    return ['success' => true, 'error' => null];
}

/**
 * Decodifica rol_y_perfiles sin importar si executeQuery ya lo devolvió
 * como array (jsonb auto-decodificado) o como string JSON crudo.
 */
function decodificarRolPerfiles($valor): array
{
    if (is_array($valor)) {
        return $valor;
    }
    if (is_string($valor) && $valor !== '') {
        $decoded = json_decode($valor, true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}

// Mismo patrón: acepta array ya decodificado o string JSON crudo.
function decodificarJsonArray($valor): array
{
    if (is_array($valor)) {
        return $valor;
    }
    if (is_string($valor) && $valor !== '') {
        $decoded = json_decode($valor, true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}

/**
 * Devuelve los nombres de etapa (en mayúsculas) a las que está asignado
 * el operario con este id (operario.id, NO usuario.id), leyendo
 * operario.js_etapas_relacionadas.
 */
function resolverEtapasOperario($conectar, int $operarioId): array
{
    if ($operarioId <= 0) return [];

    $row = executeQuery(
        $conectar,
        "SELECT js_etapas_relacionadas FROM operario WHERE id = :id",
        ['id' => $operarioId]
    );
    if (empty($row)) return [];

    $etapas = decodificarJsonArray($row[0]['js_etapas_relacionadas'] ?? null);

    return array_values(array_filter(array_map(
        fn($e) => isset($e['nombre']) ? mb_strtoupper(trim($e['nombre']), 'UTF-8') : null,
        $etapas
    )));
}

function guardarSesionOperario(array $usuario, array $etapas = []): void
{
    $rolPerfiles = decodificarRolPerfiles($usuario['rol_y_perfiles']);

    $_SESSION['operario_id']     = $usuario['id'];
    $_SESSION['operario_dni']    = $usuario['user_'];
    $_SESSION['operario_nombre'] = $usuario['nombre_completo'];
    $_SESSION['operario_rol']    = $rolPerfiles['rol'] ?? null;
    $_SESSION['operario_etapas'] = $etapas; // NUEVO
}

function cerrarSesionOperario(): void
{
    unset(
        $_SESSION['operario_id'],
        $_SESSION['operario_dni'],
        $_SESSION['operario_nombre'],
        $_SESSION['operario_rol'],
        $_SESSION['operario_etapas']
    );
}

/**
 * ¿El operario logueado tiene asignada una etapa cuyo nombre contiene este
 * fragmento? Mismo criterio ILIKE '%fragmento%' que ya usa BUSCAROPERARIOS
 * en clssEnsamblaje.php, pero contra la sesión (sin ir a la BD de nuevo).
 * Ej: operarioTieneEtapa('ENSAMBLA') o operarioTieneEtapa('PRODUC').
 */
function operarioTieneEtapa(string $fragmento): bool
{
    $fragmento = mb_strtoupper(trim($fragmento), 'UTF-8');
    $etapas = $_SESSION['operario_etapas'] ?? [];
    foreach ($etapas as $etapaNombre) {
        if (mb_strpos($etapaNombre, $fragmento, 0, 'UTF-8') !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Guard de página: exige que el operario logueado tenga la etapa dada.
 * Si no la tiene, muestra un modal bloqueante explicando por qué no puede
 * entrar y lo devuelve al panel al confirmar — NO lo redirige en silencio.
 * Debe llamarse DESPUÉS de confirmar que $_SESSION['operario_id'] existe.
 */
function exigirAccesoEtapa(string $fragmentoEtapa, string $nombreModulo): void
{
    if (operarioTieneEtapa($fragmentoEtapa)) {
        return;
    }

    $nombreOperario = $_SESSION['operario_nombre'] ?? 'Operario';
    $tituloJs   = json_encode('Sin acceso a ' . $nombreModulo, JSON_UNESCAPED_UNICODE);
    $mensajeJs  = json_encode(
        htmlspecialchars($nombreOperario) . ', tu usuario no tiene asignada la etapa de <b>' .
        htmlspecialchars($nombreModulo) . '</b>.<br>Pide a un administrador que te agregue esta etapa si deberías tener acceso.',
        JSON_UNESCAPED_UNICODE
    );
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
        <title>Acceso restringido · Plásticos Chepito</title>
    </head>
    <body style="margin:0;background:#f6f4ee;">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            Swal.fire({
                icon: 'warning',
                title: <?= $tituloJs ?>,
                html: <?= $mensajeJs ?>,
                confirmButtonText: 'Volver al panel',
                allowOutsideClick: false,
                allowEscapeKey: false,
            }).then(() => {
                window.location.href = 'panel.php';
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}